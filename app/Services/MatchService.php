<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CompetitionStatus;
use App\Enums\CompetitorStatus;
use App\Enums\MatchStatus;
use App\Enums\ResultStatus;
use App\Enums\StageFormat;
use App\Enums\StageStatus;
use App\Models\Competition;
use App\Models\Competitor;
use App\Models\SportMatch;
use App\Models\Stage;
use App\Models\User;
use App\Services\Competitions\Engine\GameScoreValidator;
use App\Support\LogsActivity;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Step 12C/12D — نتیجه‌ی بازی‌ها (لیگ دوره‌ای، گروه‌ها و جدول حذفی).
 *
 * چرخه: ثبت‌کننده نتیجه را «ثبت» می‌کند (entered) و مدیر «تأیید» می‌کند (confirmed). نتیجه‌ی مدیر یا ورزشی که
 * `requires_confirmation=false` دارد مستقیم تأییدشده است. فقط نتیجه‌ی تأییدشده در رده‌بندی می‌آید.
 * ویرایش نتیجه‌ی تأییدشده فقط با دسترسی تأیید؛ بعد از پایان ورزش همه‌چیز قفل است (بازگشایی صریح مدیر).
 * سرویس Gate صدا نمی‌زند؛ کامپوننت مجوز را می‌سنجد و `$canConfirm` را می‌دهد.
 *
 * حذفی (12D): تساوی ممکن نیست (برنده با `tiebreak` = a|b تعیین می‌شود)، نتیجه‌ی تأییدشده برنده را به بازی بعد
 * می‌برد، تغییر نتیجه‌ی پیشروی‌کرده فقط وقتی مجاز است که بازی‌های بعدی نتیجه نداشته باشند، و بازی حذفی لغو نمی‌شود
 * (برد اداری). پایان همه‌ی بازی‌های گروه‌ها جدول حذفی را خودکار پر می‌کند (BracketService).
 */
class MatchService
{
    use LogsActivity;

    public const MAX_SCORE = 999;

    public function __construct(
        private CompetitionService $competitions,
        private GameScoreValidator $games,
        private StandingsService $standings,
        private BracketService $brackets,
    ) {}

    // ----------------------------------------------------------------- نتیجه

    /**
     * @param  array<string, mixed>  $input  score_a, score_b  |  games: list<[a, b]>
     *
     * @throws InvalidArgumentException
     */
    public function enterResult(SportMatch $match, User $actor, array $input, bool $canConfirm = false): SportMatch
    {
        $competition = $this->competitionOf($match);
        $this->applyResult($match, $competition, $actor, $input, $canConfirm);
        $this->refreshState($competition);

        return $match->refresh();
    }

    /**
     * @throws InvalidArgumentException
     */
    public function confirmResult(SportMatch $match, User $actor): SportMatch
    {
        $competition = $this->competitionOf($match);
        $this->assertResultsOpen($competition);

        if ($match->result_status !== ResultStatus::Entered) {
            throw new InvalidArgumentException(__('competition.errors.nothing_to_confirm'));
        }

        DB::transaction(function () use ($match, $actor): void {
            $match->update(['result_status' => ResultStatus::Confirmed->value, 'confirmed_by' => $actor->id]);

            if ($this->brackets->isKnockout($match->stage)) {
                $this->brackets->advance($match->refresh(), $actor);
            }
        });

        self::logActivity('match.result_confirmed', $match, $competition->event_id, $this->logProps($match));

        $this->refreshState($competition);

        return $match->refresh();
    }

    /**
     * حذف نتیجه (مدیر): بازی به «برنامه‌ریزی‌شده» برمی‌گردد.
     *
     * @throws InvalidArgumentException
     */
    public function clearResult(SportMatch $match, User $actor): SportMatch
    {
        $competition = $this->competitionOf($match);
        $this->assertResultsOpen($competition);

        if ($match->result_status === null && $match->status !== MatchStatus::Walkover) {
            throw new InvalidArgumentException(__('competition.errors.no_result'));
        }

        $this->assertNotLocked($match);

        $before = $this->logProps($match);
        $wasAdvanced = $match->result_status === ResultStatus::Confirmed || $match->status === MatchStatus::Walkover;

        DB::transaction(function () use ($match, $wasAdvanced): void {
            $match->games()->delete();
            $match->update($this->emptyResult());

            if ($wasAdvanced && $this->brackets->isKnockout($match->stage)) {
                $this->brackets->retract($match->refresh());
            }
        });

        self::logActivity('match.result_cleared', $match, $competition->event_id, ['before' => $before]);

        $this->refreshState($competition);

        return $match->refresh();
    }

    /**
     * برد اداری (مدیر).
     *
     * @throws InvalidArgumentException
     */
    public function walkover(SportMatch $match, User $actor, string $winnerSide): SportMatch
    {
        $competition = $this->competitionOf($match);
        $this->assertResultsOpen($competition);
        $this->assertPlayable($match);

        if (! in_array($winnerSide, ['a', 'b'], true)) {
            throw new InvalidArgumentException(__('competition.errors.invalid_value'));
        }

        $this->assertNotLocked($match);

        DB::transaction(function () use ($match, $actor, $winnerSide): void {
            $match->games()->delete();
            $match->update([
                'status' => MatchStatus::Walkover->value,
                'winner_id' => $winnerSide === 'a' ? $match->a_id : $match->b_id,
                'score_a' => null, 'score_b' => null, 'points_a' => null, 'points_b' => null,
                'result_status' => ResultStatus::Confirmed->value,
                'result_meta' => null,
                'entered_by' => $actor->id,
                'confirmed_by' => $actor->id,
            ]);

            if ($this->brackets->isKnockout($match->stage)) {
                $this->brackets->advance($match->refresh(), $actor);
            }
        });

        self::logActivity('match.walkover', $match, $competition->event_id, $this->logProps($match));

        $this->refreshState($competition);

        return $match->refresh();
    }

    /**
     * @throws InvalidArgumentException
     */
    public function cancel(SportMatch $match, User $actor): SportMatch
    {
        $competition = $this->competitionOf($match);
        $this->assertResultsOpen($competition);
        $this->assertNoResult($match);

        if ($this->brackets->isKnockout($match->stage)) {
            throw new InvalidArgumentException(__('competition.errors.knockout_cancel_blocked'));
        }

        $match->update(['status' => MatchStatus::Cancelled->value, 'counts_in_standings' => false]);

        self::logActivity('match.cancelled', $match, $competition->event_id, []);

        $this->refreshState($competition);

        return $match->refresh();
    }

    /**
     * @throws InvalidArgumentException
     */
    public function postpone(SportMatch $match, User $actor): SportMatch
    {
        $competition = $this->competitionOf($match);
        $this->assertOpenForEdit($competition);
        $this->assertNoResult($match);

        $match->update(['status' => MatchStatus::Postponed->value, 'scheduled_at' => null, 'court' => null]);

        self::logActivity('match.postponed', $match, $competition->event_id, []);

        return $match->refresh();
    }

    // ------------------------------------------------------------ زمان‌بندی

    /**
     * زمان/زمین دستی؛ تداخل هر شرکت‌کننده (در همین ورزش) و هر زمین (در کل رویداد) رد می‌شود.
     *
     * @throws InvalidArgumentException
     */
    public function schedule(SportMatch $match, User $actor, ?CarbonInterface $at, ?string $court): SportMatch
    {
        $competition = $this->competitionOf($match);
        $this->assertOpenForEdit($competition);
        $this->assertNoResult($match);

        $court = $court !== null ? trim($court) : null;
        $court = $court === '' ? null : $court;

        if ($court !== null && mb_strlen($court) > 60) {
            throw new InvalidArgumentException(__('competition.errors.schedule_invalid'));
        }

        if ($at !== null) {
            $this->assertNoScheduleClash($match, $competition, $at, $court);
        }

        $match->update([
            'scheduled_at' => $at,
            'court' => $court,
            'status' => $match->status === MatchStatus::Postponed ? MatchStatus::Scheduled->value : $match->status->value,
        ]);

        self::logActivity('match.scheduled', $match, $competition->event_id, [
            'at' => $at?->toDateTimeString(),
            'court' => $court,
        ]);

        return $match->refresh();
    }

    // ------------------------------------------------------------ بازی دستی

    /**
     * @param  array<string, mixed>  $data  a_id, b_id, round, counts_in_standings?
     *
     * @throws InvalidArgumentException
     */
    public function addManual(Stage $stage, User $actor, array $data): SportMatch
    {
        $competition = $stage->competition;
        $this->assertOpenForEdit($competition);

        if (in_array($competition->status, [CompetitionStatus::Registration], true)
            || $stage->status === StageStatus::Pending
            || $stage->format !== StageFormat::RoundRobin) {
            throw new InvalidArgumentException(__('competition.errors.manual_not_allowed'));
        }

        $aId = (int) ($data['a_id'] ?? 0);
        $bId = (int) ($data['b_id'] ?? 0);
        $round = (int) ($data['round'] ?? 0);

        if ($aId === $bId || $round < 1 || $round > 999) {
            throw new InvalidArgumentException(__('competition.errors.manual_invalid'));
        }

        $found = Competitor::query()
            ->where('competition_id', $competition->id)
            ->whereIn('id', [$aId, $bId])
            ->where('status', '!=', CompetitorStatus::Withdrawn->value)
            ->count();

        if ($found !== 2) {
            throw new InvalidArgumentException(__('competition.errors.manual_invalid'));
        }

        $number = (int) $stage->matches()->where('round', $round)->max('number') + 1;

        $match = $stage->matches()->create([
            'round' => $round,
            'leg' => 1,
            'number' => $number,
            'a_id' => $aId,
            'b_id' => $bId,
            'status' => MatchStatus::Scheduled->value,
            'counts_in_standings' => (bool) ($data['counts_in_standings'] ?? true),
            'is_manual' => true,
        ]);

        self::logActivity('match.manual_added', $match, $competition->event_id, ['round' => $round]);

        return $match;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function deleteManual(SportMatch $match, User $actor): void
    {
        $competition = $this->competitionOf($match);
        $this->assertOpenForEdit($competition);

        if (! $match->is_manual) {
            throw new InvalidArgumentException(__('competition.errors.not_manual'));
        }
        $this->assertNoResult($match);

        self::logActivity('match.manual_deleted', $match, $competition->event_id, []);

        $match->delete();
        $this->refreshState($competition);
    }

    // -------------------------------------------------------------- ایمپورت

    /**
     * ایمپورت نتیجه‌ها از متن/CSV؛ هر خط «دور، تیم A، تیم B، گل A، گل B».
     * بازی از روی دور و نام‌ها پیدا می‌شود (جهت برعکس هم قبول است).
     *
     * @return array{applied: int, errors: list<array{line: int, message: string}>}
     *
     * @throws InvalidArgumentException
     */
    public function importResults(Competition $competition, User $actor, string $text, bool $canConfirm = false): array
    {
        $this->assertResultsOpen($competition);

        if ($competition->config('unit', 'goals') === 'games') {
            throw new InvalidArgumentException(__('competition.errors.import_games_unsupported'));
        }

        $stage = $competition->firstStage();
        if ($stage === null) {
            throw new InvalidArgumentException(__('competition.errors.no_fixtures'));
        }

        $index = [];
        foreach ($stage->matches()->with(['a', 'b'])->get() as $m) {
            if ($m->a === null || $m->b === null) {
                continue;
            }
            $index[$m->round.'|'.$this->norm($m->a->name).'|'.$this->norm($m->b->name)] = [$m, false];
            $index[$m->round.'|'.$this->norm($m->b->name).'|'.$this->norm($m->a->name)] = [$m, true];
        }

        $applied = 0;
        $errors = [];

        foreach (preg_split('/\R/u', $text) ?: [] as $i => $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $cols = array_map('trim', preg_split('/[,،;\t]/u', $line) ?: []);

            if (count($cols) !== 5 || ! ctype_digit($cols[0])) {
                $errors[] = ['line' => $i + 1, 'message' => __('competition.errors.import_line_invalid')];

                continue;
            }

            $hit = $index[(int) $cols[0].'|'.$this->norm($cols[1]).'|'.$this->norm($cols[2])] ?? null;
            if ($hit === null) {
                $errors[] = ['line' => $i + 1, 'message' => __('competition.errors.import_match_not_found')];

                continue;
            }

            [$match, $flipped] = $hit;
            [$sa, $sb] = $flipped ? [$cols[4], $cols[3]] : [$cols[3], $cols[4]];

            try {
                $this->applyResult($match->refresh(), $competition, $actor, ['score_a' => $sa, 'score_b' => $sb], $canConfirm);
                $applied++;
            } catch (InvalidArgumentException $e) {
                $errors[] = ['line' => $i + 1, 'message' => $e->getMessage()];
            }
        }

        $this->refreshState($competition);

        self::logActivity('competition.results_imported', $competition, $competition->event_id, [
            'applied' => $applied,
            'errors' => count($errors),
        ]);

        return ['applied' => $applied, 'errors' => $errors];
    }

    // --------------------------------------------------------------- انصراف

    /**
     * انصراف بعد از قرعه. سیاست در config مرحله: keep_played (پیش‌فرض: بازی‌های انجام‌شده می‌مانند،
     * بازی‌های مانده برد اداری حریف) یا void_all (همه‌ی بازی‌های او از رده‌بندی حذف می‌شود).
     *
     * @throws InvalidArgumentException
     */
    public function applyWithdrawal(Competitor $competitor, User $actor): Competitor
    {
        $competition = $competitor->competition;
        $this->assertOpenForEdit($competition);

        $stage = $competition->firstStage();
        $policy = (string) ($stage?->config('withdrawal_policy', 'keep_played') ?? 'keep_played');

        DB::transaction(function () use ($competitor, $competition, $actor, $policy): void {
            $competitor->update(['status' => CompetitorStatus::Withdrawn->value]);

            $withdrawn = Competitor::query()
                ->where('competition_id', $competition->id)
                ->where('status', CompetitorStatus::Withdrawn->value)
                ->pluck('id')->map(fn ($id) => (int) $id)->all();

            $matches = SportMatch::query()
                ->with('stage')
                ->whereIn('stage_id', $competition->stages()->pluck('id'))
                ->where(fn ($q) => $q->where('a_id', $competitor->id)->orWhere('b_id', $competitor->id))
                ->orderBy('stage_id')
                ->orderBy('round')
                ->get();

            foreach ($matches as $match) {
                // حذفی: بازی بی‌نتیجه‌ی او با حریفِ معلوم، برد اداری حریف است و پیشروی می‌کند؛
                // اگر حریف هنوز معلوم نیست، وقتی حریف در خانه نشست همین کار خودکار انجام می‌شود
                if ($match->stage->format === StageFormat::SingleElim) {
                    $this->brackets->settleWithdrawn($match, $actor);

                    continue;
                }

                $unplayed = $match->result_status === null
                    && in_array($match->status, [MatchStatus::Scheduled, MatchStatus::Postponed, MatchStatus::Live], true);

                if ($policy === 'void_all') {
                    $match->update(array_filter([
                        'counts_in_standings' => false,
                        'status' => $unplayed ? MatchStatus::Cancelled->value : null,
                    ], fn ($v) => $v !== null));

                    continue;
                }

                if (! $unplayed) {
                    continue;
                }

                $opponent = (int) $match->a_id === $competitor->id ? (int) $match->b_id : (int) $match->a_id;

                if ($opponent === 0 || in_array($opponent, $withdrawn, true)) {
                    $match->update(['status' => MatchStatus::Cancelled->value, 'counts_in_standings' => false]);

                    continue;
                }

                $match->update([
                    'status' => MatchStatus::Walkover->value,
                    'winner_id' => $opponent,
                    'result_status' => ResultStatus::Confirmed->value,
                    'entered_by' => $actor->id,
                    'confirmed_by' => $actor->id,
                ]);
            }
        });

        self::logActivity('competitor.withdrawn', $competitor, $competition->event_id, [
            'name' => $competitor->name,
            'policy' => $policy,
        ]);

        $this->refreshState($competition);

        return $competitor->refresh();
    }

    // ------------------------------------------------------- وضعیت ورزش/مرحله

    /**
     * ورزش و مرحله‌ها را از روی بازی‌ها هم‌گام می‌کند (محاسبه‌ی وضعیت از داده، نه رویداد؛ تکرارپذیر):
     *  - هر مرحله: drawn ↔ running با اولین/آخرین نتیجه.
     *  - مرحله‌ی گروهی با حذفیِ بعدی: وقتی همه‌ی بازی‌ها تأیید شد ← پایان‌یافته و جدول حذفی پر می‌شود؛
     *    اگر نتیجه‌ای حذف/باز شد ← حذفی دوباره «در انتظار» می‌شود (تا قبل از اولین نتیجه‌ی حذفی).
     *  - ورزش: ready → running با اولین نتیجه؛ پایان (با رتبه‌ی نهایی) وقتی همه‌ی بازی‌های غیرلغوشده‌ی
     *    مرحله‌ی نهایی تمام و تأییدشده‌اند.
     */
    public function refreshState(Competition $competition): void
    {
        $competition->refresh();

        if ($competition->status === CompetitionStatus::Finished) {
            return;
        }

        $stages = $competition->stages()->get()->values();

        if ($stages->isEmpty()) {
            return;
        }

        $final = $stages->firstWhere('is_final', true) ?? $stages->last();
        $anyResult = false;
        $finalDone = false;

        foreach ($stages as $i => $stage) {
            // مرحله‌ی قبلی ممکن است همین حالا این مرحله را پر کرده باشد (resolveFromGroups)
            $stage->refresh();
            $next = $stages->get($i + 1);

            $matches = $stage->matches()->get(['id', 'status', 'result_status']);
            $live = $matches->filter(fn ($m) => $m->status !== MatchStatus::Cancelled);

            $hasResult = $live->contains(fn ($m) => $m->result_status !== null || $m->status === MatchStatus::Walkover);
            $allDone = $live->isNotEmpty() && $live->every(
                fn ($m) => $m->result_status === ResultStatus::Confirmed
                    && in_array($m->status, [MatchStatus::Finished, MatchStatus::Walkover], true),
            );

            $anyResult = $anyResult || $hasResult;

            // جدول حذفیِ منتظر صعودکننده‌ها هنوز شروع نشده است
            if ($stage->status === StageStatus::Pending) {
                continue;
            }

            if ($stage->format === StageFormat::Groups && $next !== null && $next->format === StageFormat::SingleElim) {
                if ($allDone) {
                    if ($stage->status !== StageStatus::Finished) {
                        $stage->update(['status' => StageStatus::Finished->value]);
                    }

                    $this->brackets->resolveFromGroups($stage->refresh(), $next, null);
                } elseif ($stage->status === StageStatus::Finished) {
                    $this->brackets->unresolve($next);
                    $stage->update(['status' => ($hasResult ? StageStatus::Running : StageStatus::Drawn)->value]);
                } else {
                    $this->syncRunning($stage, $hasResult);
                }

                continue;
            }

            $this->syncRunning($stage, $hasResult);

            if ($stage->is($final) && $allDone) {
                $finalDone = true;
            }
        }

        if ($anyResult && $competition->status === CompetitionStatus::Ready) {
            $competition->update(['status' => CompetitionStatus::Running->value]);
        } elseif (! $anyResult && $competition->status === CompetitionStatus::Running) {
            $competition->update(['status' => CompetitionStatus::Ready->value]);
        }

        if ($finalDone && $competition->refresh()->status === CompetitionStatus::Running) {
            $this->finish($competition, $final->refresh());
        }
    }

    private function syncRunning(Stage $stage, bool $hasResult): void
    {
        if ($hasResult && $stage->status === StageStatus::Drawn) {
            $stage->update(['status' => StageStatus::Running->value]);
        } elseif (! $hasResult && $stage->status === StageStatus::Running) {
            $stage->update(['status' => StageStatus::Drawn->value]);
        }
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @param  array<string, mixed>  $input
     */
    private function applyResult(SportMatch $match, Competition $competition, User $actor, array $input, bool $canConfirm): void
    {
        $this->assertResultsOpen($competition);
        $this->assertPlayable($match);

        if (($match->result_status === ResultStatus::Confirmed || $match->status === MatchStatus::Walkover) && ! $canConfirm) {
            throw new InvalidArgumentException(__('competition.errors.confirmed_locked'));
        }

        $this->assertNotLocked($match);

        $scores = $this->resolveScores($competition, $input);
        $knockout = $this->brackets->isKnockout($match->stage);

        $winner = match (true) {
            $scores['score_a'] > $scores['score_b'] => $match->a_id,
            $scores['score_b'] > $scores['score_a'] => $match->b_id,
            default => null,
        };

        // حذفی: تساوی ممکن نیست؛ برنده‌ی ضربات پنالتی/تعیین‌کننده را کاربر صریحاً انتخاب می‌کند
        $tiebreak = null;
        if ($winner === null && $knockout) {
            $tiebreak = $input['tiebreak'] ?? null;

            if (! in_array($tiebreak, ['a', 'b'], true)) {
                throw new InvalidArgumentException(__('competition.errors.knockout_tie'));
            }

            $winner = $tiebreak === 'a' ? $match->a_id : $match->b_id;
        }

        $confirmed = $canConfirm || ! $competition->requires_confirmation;

        DB::transaction(function () use ($match, $scores, $winner, $confirmed, $actor, $knockout, $tiebreak): void {
            $match->games()->delete();

            foreach ($scores['games'] as $i => [$pa, $pb]) {
                $match->games()->create(['game_no' => $i + 1, 'points_a' => $pa, 'points_b' => $pb]);
            }

            $match->update([
                'status' => MatchStatus::Finished->value,
                'winner_id' => $winner,
                'score_a' => $scores['score_a'],
                'score_b' => $scores['score_b'],
                'points_a' => $scores['points_a'],
                'points_b' => $scores['points_b'],
                'result_status' => ($confirmed ? ResultStatus::Confirmed : ResultStatus::Entered)->value,
                'result_meta' => $tiebreak !== null ? ['tiebreak' => $tiebreak] : null,
                'entered_by' => $actor->id,
                'confirmed_by' => $confirmed ? $actor->id : null,
            ]);

            if ($knockout && $confirmed) {
                $this->brackets->advance($match->refresh(), $actor);
            }
        });

        self::logActivity('match.result_entered', $match, $competition->event_id, $this->logProps($match->refresh()));
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{score_a: int, score_b: int, points_a: int|null, points_b: int|null, games: list<array{0: int, 1: int}>}
     */
    private function resolveScores(Competition $competition, array $input): array
    {
        if ($competition->config('unit', 'goals') === 'games') {
            $result = $this->games->validate((array) ($input['games'] ?? []), [
                'best_of' => (int) $competition->config('best_of', 5),
                'points_per_game' => (int) $competition->config('points_per_game', 11),
                'win_by' => (int) $competition->config('win_by', 2),
                'max_points' => $competition->config('max_points'),
            ]);

            if (! $result->valid) {
                throw new InvalidArgumentException(__('competition.errors.'.$result->error));
            }

            return [
                'score_a' => $result->gamesA,
                'score_b' => $result->gamesB,
                'points_a' => $result->pointsA,
                'points_b' => $result->pointsB,
                'games' => $result->games,
            ];
        }

        return [
            'score_a' => $this->score($input['score_a'] ?? null),
            'score_b' => $this->score($input['score_b'] ?? null),
            'points_a' => null,
            'points_b' => null,
            'games' => [],
        ];
    }

    private function score(mixed $value): int
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        if ((! is_int($value) && ! (is_string($value) && ctype_digit($value))) || (int) $value < 0 || (int) $value > self::MAX_SCORE) {
            throw new InvalidArgumentException(__('competition.errors.score_invalid'));
        }

        return (int) $value;
    }

    private function competitionOf(SportMatch $match): Competition
    {
        return $match->stage->competition;
    }

    private function assertOpenForEdit(Competition $competition): void
    {
        $this->competitions->assertWritable($competition);

        if ($competition->status === CompetitionStatus::Finished) {
            throw new InvalidArgumentException(__('competition.errors.competition_finished'));
        }
    }

    private function assertResultsOpen(Competition $competition): void
    {
        $this->assertOpenForEdit($competition);

        if (! $competition->status->acceptsResults()) {
            throw new InvalidArgumentException(__('competition.errors.results_closed'));
        }
    }

    private function assertPlayable(SportMatch $match): void
    {
        if ($match->a_id === null || $match->b_id === null) {
            throw new InvalidArgumentException(__('competition.errors.teams_unknown'));
        }

        if ($match->status === MatchStatus::Cancelled) {
            throw new InvalidArgumentException(__('competition.errors.match_cancelled'));
        }
    }

    private function assertNoResult(SportMatch $match): void
    {
        if ($match->result_status !== null || $match->status === MatchStatus::Walkover) {
            throw new InvalidArgumentException(__('competition.errors.has_result'));
        }
    }

    private function assertNotLocked(SportMatch $match): void
    {
        if ($this->brackets->isLocked($match)) {
            throw new InvalidArgumentException(__('competition.errors.advanced_locked'));
        }
    }

    private function assertNoScheduleClash(SportMatch $match, Competition $competition, CarbonInterface $at, ?string $court): void
    {
        $minutes = (int) ($match->stage->config('match_minutes') ?? config('competitions.match_minutes', 60));

        $base = SportMatch::query()
            ->whereKeyNot($match->id)
            ->where('status', '!=', MatchStatus::Cancelled->value)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>', $at->copy()->subMinutes($minutes))
            ->where('scheduled_at', '<', $at->copy()->addMinutes($minutes));

        $teams = array_values(array_filter([$match->a_id, $match->b_id]));

        if ($teams !== [] && (clone $base)
            ->whereHas('stage', fn ($q) => $q->where('competition_id', $competition->id))
            ->where(fn ($q) => $q->whereIn('a_id', $teams)->orWhereIn('b_id', $teams))
            ->exists()) {
            throw new InvalidArgumentException(__('competition.errors.schedule_competitor_clash'));
        }

        if ($court !== null && (clone $base)
            ->where('court', $court)
            ->whereHas('stage.competition', fn ($q) => $q->where('event_id', $competition->event_id))
            ->exists()) {
            throw new InvalidArgumentException(__('competition.errors.schedule_court_clash'));
        }
    }

    private function finish(Competition $competition, Stage $stage): void
    {
        DB::transaction(function () use ($competition, $stage): void {
            $ranks = [];

            if ($this->brackets->isKnockout($stage)) {
                $ranks = $this->brackets->finalRanks($stage);
            } else {
                foreach ($this->standings->forStage($stage) as $row) {
                    $ranks[(int) $row['competitor_id']] = (int) $row['rank'];
                }
            }

            foreach ($ranks as $competitorId => $rank) {
                Competitor::query()->whereKey($competitorId)->update(['final_rank' => $rank]);
                $stage->entries()->where('competitor_id', $competitorId)->update(['final_rank' => $rank]);
            }

            $stage->update(['status' => StageStatus::Finished->value]);
            $competition->update(['status' => CompetitionStatus::Finished->value]);
        });

        self::logActivity('competition.finished', $competition, $competition->event_id, []);
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyResult(): array
    {
        return [
            'status' => MatchStatus::Scheduled->value,
            'winner_id' => null,
            'score_a' => null, 'score_b' => null, 'points_a' => null, 'points_b' => null,
            'result_status' => null, 'result_meta' => null, 'entered_by' => null, 'confirmed_by' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function logProps(SportMatch $match): array
    {
        return [
            'a_id' => $match->a_id, 'b_id' => $match->b_id,
            'score_a' => $match->score_a, 'score_b' => $match->score_b,
            'status' => $match->status->value,
            'result_status' => $match->result_status?->value,
        ];
    }

    private function norm(string $name): string
    {
        return mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($name)));
    }
}
