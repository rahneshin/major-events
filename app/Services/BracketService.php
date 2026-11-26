<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CompetitorStatus;
use App\Enums\MatchStatus;
use App\Enums\ResultStatus;
use App\Enums\StageFormat;
use App\Enums\StageStatus;
use App\Models\Competitor;
use App\Models\SportMatch;
use App\Models\Stage;
use App\Models\User;
use App\Services\Competitions\Engine\BracketGenerator;
use App\Services\Competitions\Engine\GroupSplitter;
use Illuminate\Support\Facades\DB;

/**
 * Step 12D — جدول حذفی تک‌شکست و پیوند «گروهی ← حذفی».
 *
 *  - ساخت ردیف بازی‌ها از خروجی `BracketGenerator` (بازی‌های بای ساخته نمی‌شوند).
 *  - پیشروی: با هر نتیجه‌ی تأییدشده برنده در `next_match` (و بازنده در `loser_next_match`) می‌نشیند.
 *  - قفل ویرایش: نتیجه‌ای که پیشروی کرده فقط وقتی تغییر می‌کند که بازی‌های بعدی نتیجه نداشته باشند
 *    (جایگزینی خودکار)؛ وگرنه «ابتدا نتیجه‌ی بازی بعدی را لغو کنید».
 *  - گروهی ← حذفی: وقتی همه‌ی بازی‌های گروه‌ها تأیید شد، خانه‌های `group_rank` با رده‌بندی هر گروه پر می‌شود
 *    (تساوی باقی‌مانده با قرعه‌ی ثبت‌شده‌ی مرحله حل می‌شود). تا قبل از اولین نتیجه‌ی حذفی برگشت‌پذیر است.
 *  - انصراف: حریف شرکت‌کننده‌ی منصرف‌شده برنده‌ی اداری می‌شود و پیشروی می‌کند.
 *
 * سرویس Gate صدا نمی‌زند؛ مجوز را کامپوننت می‌سنجد.
 */
class BracketService
{
    public function __construct(
        private BracketGenerator $generator,
        private GroupSplitter $splitter,
        private StandingsService $standings,
    ) {}

    public function isKnockout(Stage $stage): bool
    {
        return $stage->format === StageFormat::SingleElim;
    }

    public function hasResult(SportMatch $match): bool
    {
        return $match->result_status !== null || $match->status === MatchStatus::Walkover;
    }

    public function nextStage(Stage $stage): ?Stage
    {
        return Stage::query()
            ->where('competition_id', $stage->competition_id)
            ->where('position', '>', $stage->position)
            ->orderBy('position')
            ->first();
    }

    // ------------------------------------------------------------------ ساخت

    /**
     * ساخت بازی‌های جدول حذفی.
     *
     * @param  array{size: int, rounds: int, third_place: bool, matches: list<array<string, mixed>>}  $plan  خروجی BracketGenerator::generate
     * @param  array<int, array{id: int|null, source: array<string, mixed>}>  $seeds  بذر ← خانه (شناسه‌ی شرکت‌کننده یا منبع)
     * @return int تعداد بازی‌های ساخته‌شده
     */
    public function createBracket(Stage $stage, array $plan, array $seeds): int
    {
        /** @var array<string, SportMatch> $created */
        $created = [];

        DB::transaction(function () use ($stage, $plan, $seeds, &$created): void {
            foreach ($plan['matches'] as $m) {
                $slots = [];

                foreach (['a', 'b'] as $side) {
                    $ref = $m[$side];

                    if (isset($ref['seed'])) {
                        $slots[$side] = $seeds[$ref['seed']] ?? ['id' => null, 'source' => ['seed' => $ref['seed']]];
                    } elseif (isset($ref['winner_of'])) {
                        $slots[$side] = ['id' => null, 'source' => ['winner_of' => 'R'.$ref['winner_of']['round'].'M'.$ref['winner_of']['number']]];
                    } else {
                        $slots[$side] = ['id' => null, 'source' => ['loser_of' => 'R'.$ref['loser_of']['round'].'M'.$ref['loser_of']['number']]];
                    }
                }

                $created[$m['round'].'-'.$m['number']] = $stage->matches()->create([
                    'round' => $m['round'],
                    'leg' => 1,
                    'number' => $m['number'],
                    'bracket' => $m['bracket'],
                    'a_id' => $slots['a']['id'],
                    'b_id' => $slots['b']['id'],
                    'a_source' => $slots['a']['source'],
                    'b_source' => $slots['b']['source'],
                    'status' => MatchStatus::Scheduled->value,
                    'counts_in_standings' => false,
                    'is_manual' => false,
                ]);
            }

            foreach ($plan['matches'] as $m) {
                $links = [];

                if ($m['next'] !== null) {
                    $links['next_match_id'] = $created[$m['next']['round'].'-'.$m['next']['number']]->id;
                    $links['next_slot'] = $m['next']['slot'];
                }

                if ($m['loser_next'] !== null) {
                    $links['loser_next_match_id'] = $created[$m['loser_next']['round'].'-'.$m['loser_next']['number']]->id;
                    $links['loser_next_slot'] = $m['loser_next']['slot'];
                }

                if ($links !== []) {
                    $created[$m['round'].'-'.$m['number']]->update($links);
                }
            }

            $stage->update(['config' => array_merge($stage->config ?? [], [
                'bracket_size' => $plan['size'],
                'bracket_rounds' => $plan['rounds'],
                'third_place' => $plan['third_place'],
            ])]);
        });

        return count($created);
    }

    /**
     * خانه‌های بذر برای جدول حذفی بعد از مرحله‌ی گروهی (هنوز شرکت‌کننده‌ای مشخص نیست).
     *
     * @return array<int, array{id: int|null, source: array<string, mixed>}>
     */
    public function groupFedSeeds(int $groups, int $advance): array
    {
        $seeds = [];

        foreach ($this->generator->qualifierSeeding($groups, $advance) as $i => $qualifier) {
            $seed = $i + 1;
            $seeds[$seed] = [
                'id' => null,
                'source' => ['group_rank' => [$this->splitter->name($qualifier['group']), $qualifier['rank']], 'seed' => $seed],
            ];
        }

        return $seeds;
    }

    // --------------------------------------------------------------- پیشروی

    /**
     * بعد از نتیجه‌ی تأییدشده/برد اداری: برنده (و بازنده برای بازی ردهبندی) در خانه‌ی بعدی می‌نشیند.
     */
    public function advance(SportMatch $match, ?User $actor): void
    {
        if ($match->winner_id === null || $match->a_id === null || $match->b_id === null) {
            return;
        }

        $winner = (int) $match->winner_id;
        $loser = $winner === (int) $match->a_id ? (int) $match->b_id : (int) $match->a_id;

        $this->place($match->next_match_id, $match->next_slot, $winner, $actor);
        $this->place($match->loser_next_match_id, $match->loser_next_slot, $loser, $actor);
    }

    /**
     * بعد از حذف نتیجه: خانه‌های بعدی خالی می‌شود (قفل قبلاً بررسی شده است).
     */
    public function retract(SportMatch $match): void
    {
        $this->setSlot($match->next_match_id, $match->next_slot, null);
        $this->setSlot($match->loser_next_match_id, $match->loser_next_slot, null);
    }

    /**
     * آیا تغییر نتیجه‌ی این بازی ممنوع است؟
     *  - حذفی: فقط وقتی این بازی پیشروی کرده و بازی بعدی (یا ردهبندی) نتیجه دارد.
     *  - گروهی: وقتی مرحله‌ی بعد (حذفی) نتیجه‌ای دارد.
     */
    public function isLocked(SportMatch $match): bool
    {
        $stage = $match->stage;

        if ($stage->format === StageFormat::SingleElim) {
            if ($match->result_status !== ResultStatus::Confirmed && $match->status !== MatchStatus::Walkover) {
                return false;
            }

            foreach ([$match->next_match_id, $match->loser_next_match_id] as $id) {
                if ($id === null) {
                    continue;
                }

                $target = SportMatch::query()->find($id);

                if ($target !== null && $this->hasResult($target)) {
                    return true;
                }
            }

            return false;
        }

        if ($stage->format === StageFormat::Groups) {
            $next = $this->nextStage($stage);

            return $next !== null && $this->stageHasResults($next);
        }

        return false;
    }

    /**
     * اگر دقیقاً یکی از دو طرف بازی منصرف شده، حریف برنده‌ی اداری می‌شود و پیشروی می‌کند.
     */
    public function settleWithdrawn(SportMatch $match, ?User $actor): void
    {
        $match->refresh();

        if ($this->hasResult($match)
            || $match->status === MatchStatus::Cancelled
            || $match->a_id === null
            || $match->b_id === null) {
            return;
        }

        $withdrawn = Competitor::query()
            ->whereIn('id', [$match->a_id, $match->b_id])
            ->where('status', CompetitorStatus::Withdrawn->value)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if (count($withdrawn) !== 1) {
            return;
        }

        $winner = in_array((int) $match->a_id, $withdrawn, true) ? (int) $match->b_id : (int) $match->a_id;

        $match->update([
            'status' => MatchStatus::Walkover->value,
            'winner_id' => $winner,
            'result_status' => ResultStatus::Confirmed->value,
            'entered_by' => $actor?->id,
            'confirmed_by' => $actor?->id,
        ]);

        $this->advance($match->refresh(), $actor);
    }

    // ------------------------------------------------------- گروهی ← حذفی

    /**
     * پر کردن خانه‌های `group_rank` جدول حذفی از رده‌بندی گروه‌ها؛ تکرارپذیر (idempotent).
     * اگر مرحله‌ی حذفی نتیجه‌ای دارد دست نمی‌زند (قفل).
     */
    public function resolveFromGroups(Stage $groups, Stage $knockout, ?User $actor = null): void
    {
        if ($this->stageHasResults($knockout)) {
            return;
        }

        $advance = (int) $groups->config('advance_count', 2);
        $lookup = [];

        foreach ($this->standings->forGroups($groups, true) as $name => $rows) {
            foreach (array_slice($rows, 0, $advance) as $i => $row) {
                $lookup[$name][$i + 1] = (int) $row['competitor_id'];
            }
        }

        DB::transaction(function () use ($knockout, $lookup, $actor): void {
            $seedOf = [];

            foreach ($knockout->matches()->orderBy('round')->orderBy('number')->get() as $match) {
                $updates = [];

                foreach (['a', 'b'] as $side) {
                    $source = $match->{$side.'_source'};

                    if (! is_array($source) || ! isset($source['group_rank'])) {
                        continue;
                    }

                    [$name, $rank] = $source['group_rank'];
                    $id = $lookup[$name][$rank] ?? null;
                    $updates[$side.'_id'] = $id;

                    if ($id !== null) {
                        $seedOf[$id] = (int) ($source['seed'] ?? 0);
                    }
                }

                if ($updates !== []) {
                    $match->update($updates);
                }
            }

            $knockout->entries()->delete();

            foreach ($seedOf as $competitorId => $seed) {
                $knockout->entries()->create([
                    'competitor_id' => $competitorId,
                    'seed' => $seed > 0 ? $seed : null,
                    'draw_position' => $seed > 0 ? $seed : null,
                ]);
            }

            if ($knockout->status === StageStatus::Pending) {
                $knockout->update(['status' => StageStatus::Drawn->value]);
            }

            // صعودکننده‌ی منصرف‌شده: حریفش برنده‌ی اداری می‌شود
            foreach ($knockout->matches()->orderBy('round')->orderBy('number')->get() as $match) {
                $this->settleWithdrawn($match, $actor);
            }
        });
    }

    /**
     * برگرداندن مرحله‌ی حذفی به «در انتظار صعودکننده‌ها» (وقتی نتیجه‌ی یک گروه حذف/باز شد).
     */
    public function unresolve(Stage $knockout): void
    {
        if ($this->stageHasResults($knockout)) {
            return;
        }

        DB::transaction(function () use ($knockout): void {
            foreach ($knockout->matches()->get() as $match) {
                $updates = [];

                foreach (['a', 'b'] as $side) {
                    $source = $match->{$side.'_source'};

                    if (is_array($source) && isset($source['group_rank'])) {
                        $updates[$side.'_id'] = null;
                    }
                }

                if ($updates !== []) {
                    $match->update($updates);
                }
            }

            $knockout->entries()->delete();
            $knockout->update(['status' => StageStatus::Pending->value]);
        });
    }

    // ------------------------------------------------------------ رتبه‌ی نهایی

    /**
     * رتبه‌ی نهایی مرحله‌ی حذفی (شناسه‌ی شرکت‌کننده ← رتبه).
     *
     * @return array<int, int>
     */
    public function finalRanks(Stage $stage): array
    {
        $size = (int) $stage->config('bracket_size', 0);

        if ($size < 2) {
            $size = $this->generator->sizeFor(max(2, $stage->entries()->count()));
        }

        $results = [];

        $matches = $stage->matches()
            ->whereNotNull('winner_id')
            ->whereNotNull('a_id')
            ->whereNotNull('b_id')
            ->where('result_status', ResultStatus::Confirmed->value)
            ->where('status', '!=', MatchStatus::Cancelled->value)
            ->get();

        foreach ($matches as $match) {
            $winner = (int) $match->winner_id;

            $results[] = [
                'round' => (int) $match->round,
                'bracket' => (string) $match->bracket,
                'winner' => $winner,
                'loser' => $winner === (int) $match->a_id ? (int) $match->b_id : (int) $match->a_id,
            ];
        }

        return $this->generator->ranks($size, $results);
    }

    public function stageHasResults(Stage $stage): bool
    {
        return $stage->matches()
            ->where(fn ($q) => $q->whereNotNull('result_status')->orWhere('status', MatchStatus::Walkover->value))
            ->exists();
    }

    // ---------------------------------------------------------------- helpers

    private function place(?int $matchId, ?string $slot, int $competitorId, ?User $actor): void
    {
        if ($matchId === null) {
            return;
        }

        $target = SportMatch::query()->find($matchId);

        if ($target === null) {
            return;
        }

        $column = $slot === 'b' ? 'b_id' : 'a_id';
        $target->update([$column => $competitorId]);

        $this->settleWithdrawn($target, $actor);
    }

    private function setSlot(?int $matchId, ?string $slot, ?int $competitorId): void
    {
        if ($matchId === null) {
            return;
        }

        $column = $slot === 'b' ? 'b_id' : 'a_id';

        SportMatch::query()->whereKey($matchId)->update([$column => $competitorId]);
    }
}
