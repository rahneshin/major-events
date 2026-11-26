<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CompetitionStatus;
use App\Enums\CompetitorStatus;
use App\Enums\StageFormat;
use App\Enums\StageStatus;
use App\Models\Competition;
use App\Models\Stage;
use App\Models\User;
use App\Services\Competitions\Engine\BracketGenerator;
use App\Services\Competitions\Engine\DrawEngine;
use App\Services\Competitions\Engine\GroupSplitter;
use App\Services\Competitions\Engine\RoundRobinGenerator;
use App\Services\Competitions\Engine\SeededRandom;
use App\Support\LogsActivity;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Step 12B/12D — قرعه‌کشی و ساخت جدول بازی‌ها: لیگ دوره‌ای، حذفی تک‌شکست و گروهی ← حذفی.
 *
 * قرعه = «پیش‌نمایش» (بدون ذخیره، از روی seed قابل بازتولید) + «تأیید» (ذخیره‌ی سند و خانه‌ها).
 * ساخت جدول جدا از قرعه است. بعد از اولین نتیجه‌ی تأییدشده هر دو قفل‌اند؛ بازنشانی فقط قبل از آن.
 *
 * گزینه‌های قرعه (`$options`): `groups` و `advance` (مرحله‌ی گروهی)، `third_place` (جدول حذفی).
 *  - حذفی تک‌شکست: جایگاه قرعه = بذر؛ بذرهای بالا بای می‌گیرند.
 *  - گروهی: seeded = پخش مارپیچی بر اساس سید، random = پخش دور‌به‌دور از ترتیب تصادفی.
 *  - جدول حذفیِ بعد از گروهی همراه جدول گروه‌ها ساخته می‌شود (خانه‌ها «منبع» دارند: اول گروه A، …)
 *    و بعد از پایان گروه‌ها خودکار پر می‌شود (BracketService::resolveFromGroups).
 */
class DrawService
{
    use LogsActivity;

    public const MIN_COMPETITORS = 2;

    public function __construct(
        private CompetitionService $competitions,
        private DrawEngine $engine,
        private RoundRobinGenerator $roundRobin,
        private GroupSplitter $splitter,
        private BracketGenerator $bracket,
        private BracketService $brackets,
    ) {}

    /**
     * پیش‌نمایش قرعه؛ چیزی ذخیره نمی‌شود.
     *
     * @param  array<string, mixed>  $options  groups, advance, third_place
     * @return array{method: string, seed: string, order: list<int>, names: array<int, string>, groups: array<string, list<int>>|null, options: array<string, mixed>}
     *
     * @throws InvalidArgumentException
     */
    public function preview(Competition $competition, string $method, ?string $seed = null, array $options = []): array
    {
        $stage = $this->drawableStage($competition);
        $competitors = $this->eligible($competition);

        $result = $this->engine->draw(
            $competitors->map(fn ($c) => ['id' => $c->id, 'seed' => $c->seed])->all(),
            $method,
            $seed,
        );

        $this->assertSupported($stage);

        $resolved = $this->resolveOptions($stage, $result['order'], $result['method'], $options);

        return $result + [
            'names' => $competitors->pluck('name', 'id')->all(),
            'groups' => $resolved['groups'],
            'options' => $resolved['options'],
        ];
    }

    /**
     * تأیید قرعه با همان seed پیش‌نمایش (نتیجه بازتولید می‌شود، نه دوباره‌قرعه).
     *
     * @param  array<string, mixed>  $options  groups, advance, third_place
     *
     * @throws InvalidArgumentException
     */
    public function confirm(Competition $competition, User $actor, string $method, string $seed, array $options = []): Stage
    {
        $this->competitions->assertWritable($competition);

        return DB::transaction(function () use ($competition, $actor, $method, $seed, $options): Stage {
            // قفل ردیف: دو تأیید هم‌زمان قرعه‌ی دوم را می‌سازند
            $locked = Competition::query()->whereKey($competition->id)->lockForUpdate()->firstOrFail();
            $stage = $this->drawableStage($locked);
            $this->assertSupported($stage);

            $result = $this->preview($locked, $method, $seed, $options);

            $stage->draws()->create([
                'method' => $result['method'],
                'rng_seed' => $result['seed'],
                'constraints' => $result['options'] !== [] ? $result['options'] : null,
                'result' => ['order' => $result['order']] + ($result['groups'] !== null ? ['groups' => $result['groups']] : []),
                'performed_by' => $actor->id,
            ]);

            $seeds = $locked->competitors()->whereIn('id', $result['order'])->pluck('seed', 'id');

            $groupOf = [];
            foreach ($result['groups'] ?? [] as $name => $ids) {
                foreach ($ids as $id) {
                    $groupOf[$id] = $name;
                }
            }

            foreach ($result['order'] as $i => $competitorId) {
                $stage->entries()->create([
                    'competitor_id' => $competitorId,
                    'seed' => $seeds[$competitorId] ?? null,
                    'draw_position' => $i + 1,
                    'group_name' => $groupOf[$competitorId] ?? null,
                ]);
            }

            // seed «قرعه‌ی مساوی» رده‌بندی همین‌جا ثبت می‌شود تا تساوی‌ها قابل‌بازتولید حل شوند
            $config = ['lots_seed' => SeededRandom::newSeed()];

            if ($stage->format === StageFormat::Groups) {
                $config['group_count'] = (int) $result['options']['groups'];
                $config['advance_count'] = (int) $result['options']['advance'];
            }

            if ($stage->format === StageFormat::SingleElim) {
                $config['third_place'] = (bool) ($result['options']['third_place'] ?? false);
            }

            $stage->update([
                'status' => StageStatus::Drawn->value,
                'config' => array_merge($stage->config ?? [], $config),
            ]);

            // گروهی ← حذفی: انتخاب «بازی ردهبندی» روی مرحله‌ی حذفیِ بعدی ثبت می‌شود
            if ($stage->format === StageFormat::Groups && array_key_exists('third_place', $result['options'])) {
                $knockout = $this->brackets->nextStage($stage);

                if ($knockout !== null && $knockout->format === StageFormat::SingleElim) {
                    $knockout->update(['config' => array_merge($knockout->config ?? [], [
                        'third_place' => (bool) $result['options']['third_place'],
                    ])]);
                }
            }

            $locked->update(['status' => CompetitionStatus::Drawn->value]);

            self::logActivity('competition.drawn', $locked, $locked->event_id, [
                'stage_id' => $stage->id,
                'method' => $result['method'],
                'seed' => $result['seed'],
                'count' => count($result['order']),
                'options' => $result['options'],
            ]);

            return $stage->refresh();
        });
    }

    /**
     * ساخت بازی‌های مرحله‌ی قرعه‌شده (لیگ، گروه‌ها یا جدول حذفی). دسته‌ای و داخل تراکنش.
     * برای «گروهی ← حذفی» جدول حذفی (با خانه‌های بدون شرکت‌کننده) هم همین‌جا ساخته می‌شود.
     *
     * @return int تعداد بازی‌های ساخته‌شده (همه‌ی مرحله‌ها)
     *
     * @throws InvalidArgumentException
     */
    public function generateFixtures(Competition $competition, User $actor): int
    {
        $this->competitions->assertWritable($competition);

        $stage = $competition->stages()->where('status', StageStatus::Drawn->value)->first();
        if ($stage === null) {
            throw new InvalidArgumentException(__('competition.errors.not_drawn'));
        }
        $this->assertSupported($stage);

        if ($stage->matches()->exists()) {
            throw new InvalidArgumentException(__('competition.errors.fixtures_exist'));
        }

        $entries = $stage->entries()->get();

        $count = DB::transaction(fn (): int => match ($stage->format) {
            StageFormat::Groups => $this->generateGroups($stage, $entries),
            StageFormat::SingleElim => $this->generateSingleElim($stage, $entries),
            default => $this->generateRoundRobin($stage, $entries),
        });

        self::logActivity('competition.fixtures_generated', $competition, $competition->event_id, [
            'stage_id' => $stage->id,
            'matches' => $count,
        ]);

        return $count;
    }

    /**
     * بازنشانی قرعه و جدول؛ فقط قبل از اولین نتیجه‌ی تأییدشده.
     *
     * @throws InvalidArgumentException
     */
    public function reset(Competition $competition, User $actor): void
    {
        $this->competitions->assertWritable($competition);

        if ($competition->hasConfirmedResults()) {
            throw new InvalidArgumentException(__('competition.errors.locked_by_results'));
        }

        DB::transaction(function () use ($competition): void {
            foreach ($competition->stages as $stage) {
                $stage->matches()->delete();
                $stage->entries()->delete();
                $stage->draws()->delete();
                $stage->update([
                    'status' => StageStatus::Pending->value,
                    'config' => collect($stage->config ?? [])->except(['lots_seed', 'bracket_size', 'bracket_rounds'])->all(),
                ]);
            }

            $competition->update(['status' => CompetitionStatus::Registration->value]);
        });

        self::logActivity('competition.draw_reset', $competition, $competition->event_id, []);
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @param  Collection<int, \App\Models\StageEntry>  $entries
     */
    private function generateRoundRobin(Stage $stage, Collection $entries): int
    {
        $ids = $entries->sortBy('draw_position')->pluck('competitor_id')->map(fn ($id) => (int) $id)->values()->all();
        $legs = (int) $stage->config('legs', 1);

        $plan = $this->roundRobin->generate($ids, $legs === 2 ? 2 : 1);
        $now = now();

        $rows = array_map(fn (array $f): array => [
            'uuid' => (string) Str::uuid(),
            'stage_id' => $stage->id,
            'round' => $f['round'],
            'leg' => $f['leg'],
            'number' => $f['number'],
            'a_id' => $f['a'],
            'b_id' => $f['b'],
            'status' => 'scheduled',
            'counts_in_standings' => true,
            'is_manual' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ], $plan['fixtures']);

        $this->insertRows($rows);

        return count($rows);
    }

    /**
     * لیگ دوره‌ای داخل هر گروه (شماره‌ی دور برای هر گروه از ۱) + جدول حذفیِ بعدی با خانه‌های «منبع‌دار».
     *
     * @param  Collection<int, \App\Models\StageEntry>  $entries
     */
    private function generateGroups(Stage $stage, Collection $entries): int
    {
        $legs = (int) $stage->config('legs', 1) === 2 ? 2 : 1;
        $now = now();
        $rows = [];
        $numbers = [];

        foreach ($entries->groupBy('group_name')->sortKeys() as $name => $groupEntries) {
            $ids = $groupEntries->sortBy('draw_position')->pluck('competitor_id')->map(fn ($id) => (int) $id)->values()->all();
            $plan = $this->roundRobin->generate($ids, $legs);

            foreach ($plan['fixtures'] as $f) {
                $numbers[$f['round']] = ($numbers[$f['round']] ?? 0) + 1;

                $rows[] = [
                    'uuid' => (string) Str::uuid(),
                    'stage_id' => $stage->id,
                    'group_name' => (string) $name,
                    'round' => $f['round'],
                    'leg' => $f['leg'],
                    'number' => $numbers[$f['round']],
                    'a_id' => $f['a'],
                    'b_id' => $f['b'],
                    'status' => 'scheduled',
                    'counts_in_standings' => true,
                    'is_manual' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        $this->insertRows($rows);

        $count = count($rows);
        $knockout = $this->brackets->nextStage($stage);

        if ($knockout !== null && $knockout->format === StageFormat::SingleElim && ! $knockout->matches()->exists()) {
            $groups = (int) $stage->config('group_count', 0);
            $advance = (int) $stage->config('advance_count', 2);

            $plan = $this->bracket->generate($groups * $advance, (bool) $knockout->config('third_place', false));
            $count += $this->brackets->createBracket($knockout, $plan, $this->brackets->groupFedSeeds($groups, $advance));
        }

        return $count;
    }

    /**
     * @param  Collection<int, \App\Models\StageEntry>  $entries
     */
    private function generateSingleElim(Stage $stage, Collection $entries): int
    {
        $ids = $entries->sortBy('draw_position')->pluck('competitor_id')->map(fn ($id) => (int) $id)->values()->all();
        $plan = $this->bracket->generate(count($ids), (bool) $stage->config('third_place', false));

        $seeds = [];
        foreach ($ids as $i => $id) {
            $seeds[$i + 1] = ['id' => $id, 'source' => ['seed' => $i + 1]];
        }

        return $this->brackets->createBracket($stage, $plan, $seeds);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function insertRows(array $rows): void
    {
        DB::transaction(function () use ($rows): void {
            foreach (array_chunk($rows, 100) as $chunk) {
                DB::table('matches')->insert($chunk);
            }
        });
    }

    /**
     * اعتبارسنجی گزینه‌های قرعه برای فرمت مرحله و تقسیم گروه‌ها.
     *
     * @param  list<int>  $order
     * @param  array<string, mixed>  $options
     * @return array{groups: array<string, list<int>>|null, options: array<string, mixed>}
     *
     * @throws InvalidArgumentException
     */
    private function resolveOptions(Stage $stage, array $order, string $method, array $options): array
    {
        $count = count($order);

        if ($stage->format === StageFormat::SingleElim) {
            if ($count > BracketGenerator::MAX_ENTRANTS) {
                throw new InvalidArgumentException(__('competition.errors.groups_invalid'));
            }

            return [
                'groups' => null,
                'options' => ['third_place' => $this->wantsThird($options, $stage, $count)],
            ];
        }

        if ($stage->format !== StageFormat::Groups) {
            return ['groups' => null, 'options' => []];
        }

        $groups = (int) ($options['groups'] ?? $stage->config('group_count') ?? 0);
        $advance = (int) ($options['advance'] ?? $stage->config('advance_count') ?? 2);

        if ($groups < 2
            || $groups > GroupSplitter::MAX_GROUPS
            || $count < $groups * GroupSplitter::MIN_GROUP_SIZE
            || $advance < 1
            || $advance >= intdiv($count, $groups)
            || $groups * $advance > BracketGenerator::MAX_ENTRANTS) {
            throw new InvalidArgumentException(__('competition.errors.groups_invalid'));
        }

        $named = [];
        foreach ($this->splitter->split($order, $groups, $method) as $i => $ids) {
            $named[$this->splitter->name($i)] = $ids;
        }

        $resolved = ['groups' => $groups, 'advance' => $advance];

        $knockout = $this->brackets->nextStage($stage);
        if ($knockout !== null && $knockout->format === StageFormat::SingleElim) {
            $resolved['third_place'] = $this->wantsThird($options, $knockout, $groups * $advance);
        }

        return ['groups' => $named, 'options' => $resolved];
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function wantsThird(array $options, Stage $stage, int $entrants): bool
    {
        return (bool) ($options['third_place'] ?? $stage->config('third_place', false)) && $entrants >= 4;
    }

    private function drawableStage(Competition $competition): Stage
    {
        if ($competition->status !== CompetitionStatus::Registration) {
            throw new InvalidArgumentException(__('competition.errors.already_drawn'));
        }

        $stage = $competition->firstStage();
        if ($stage === null || $stage->status !== StageStatus::Pending) {
            throw new InvalidArgumentException(__('competition.errors.already_drawn'));
        }

        return $stage;
    }

    private function assertSupported(Stage $stage): void
    {
        if (! $stage->format->isSupported()) {
            throw new InvalidArgumentException(__('competition.errors.format_not_supported'));
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, \App\Models\Competitor>
     */
    private function eligible(Competition $competition): \Illuminate\Support\Collection
    {
        $competitors = $competition->competitors()
            ->where('status', CompetitorStatus::Confirmed->value)
            ->orderBy('id')
            ->get();

        if ($competitors->count() < self::MIN_COMPETITORS) {
            throw new InvalidArgumentException(__('competition.errors.too_few', ['min' => self::MIN_COMPETITORS]));
        }

        return $competitors;
    }
}
