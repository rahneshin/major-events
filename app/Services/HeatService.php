<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CompetitionStatus;
use App\Enums\CompetitorStatus;
use App\Enums\HeatResultStatus;
use App\Enums\ResultStatus;
use App\Enums\StageStatus;
use App\Models\Competition;
use App\Models\Competitor;
use App\Models\Heat;
use App\Models\HeatResult;
use App\Models\Stage;
use App\Models\User;
use App\Services\Competitions\Engine\DrawEngine;
use App\Services\Competitions\Engine\HeatAdvancer;
use App\Services\Competitions\Engine\HeatRanker;
use App\Support\HeatValue;
use App\Support\LogsActivity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Step 12E — ورزش‌های رتبه‌ای (`result_type = ranked`): سری‌ها، ثبت نتیجه (زمان/مسافت/وزن)،
 * رتبه‌ی سری، صعود به مرحله‌ی بعد و رتبه‌ی نهایی ورزش.
 *
 * گردش: ثبت‌نام ← قرعه (ساخت سری‌ها) ← آماده ← ثبت/تأیید نتیجه‌ی هر سری ← «ساخت مرحله‌ی بعد» ← … ← فینال.
 * وقتی همه‌ی سری‌های مرحله‌ی نهایی تأیید شد، رتبه‌ی نهایی (`competitors.final_rank`) خودکار پر و ورزش پایان‌یافته می‌شود.
 *
 * مجوز در لایه‌ی Livewire با CompetitionPolicy سنجیده می‌شود (`$canConfirm` را همان‌جا می‌دهیم).
 */
class HeatService
{
    use LogsActivity;

    public const MIN_COMPETITORS = 2;

    public const DEFAULT_HEAT_SIZE = 8;

    public const MAX_HEAT_SIZE = 64;

    public function __construct(
        private CompetitionService $competitions,
        private DrawEngine $engine,
        private HeatRanker $ranker,
        private HeatAdvancer $advancer,
    ) {}

    // ------------------------------------------------------------------ قرعه

    /**
     * پیش‌نمایش قرعه‌ی سری‌ها؛ چیزی ذخیره نمی‌شود.
     *
     * @return array{method: string, seed: string, order: list<int>, heats: list<list<int>>, names: array<int, string>}
     *
     * @throws InvalidArgumentException
     */
    public function preview(Competition $competition, string $method, ?string $seed = null): array
    {
        $this->assertRanked($competition);
        $stage = $this->drawableStage($competition);
        $competitors = $this->eligible($competition);

        $draw = $this->engine->draw(
            $competitors->map(fn (Competitor $c): array => ['id' => $c->id, 'seed' => $c->seed])->all(),
            $method,
            $seed,
        );

        return [
            'method' => $draw['method'],
            'seed' => $draw['seed'],
            'order' => $draw['order'],
            'heats' => $this->distribute($draw['order'], $this->heatCount($stage, count($draw['order']))),
            'names' => $competitors->pluck('name', 'id')->all(),
        ];
    }

    /**
     * تأیید قرعه با همان seed پیش‌نمایش و ساخت سری‌های مرحله‌ی اول.
     *
     * @throws InvalidArgumentException
     */
    public function draw(Competition $competition, User $actor, string $method, string $seed): Stage
    {
        $this->competitions->assertWritable($competition);
        $this->assertRanked($competition);

        return DB::transaction(function () use ($competition, $actor, $method, $seed): Stage {
            $locked = Competition::query()->whereKey($competition->id)->lockForUpdate()->firstOrFail();
            $stage = $this->drawableStage($locked);
            $plan = $this->preview($locked, $method, $seed);

            $stage->draws()->create([
                'method' => $plan['method'],
                'rng_seed' => $plan['seed'],
                'constraints' => null,
                'result' => ['order' => $plan['order'], 'heats' => $plan['heats']],
                'performed_by' => $actor->id,
            ]);

            $seeds = $locked->competitors()->whereIn('id', $plan['order'])->pluck('seed', 'id');

            foreach ($plan['order'] as $i => $competitorId) {
                $stage->entries()->create([
                    'competitor_id' => $competitorId,
                    'seed' => $seeds[$competitorId] ?? null,
                    'draw_position' => $i + 1,
                ]);
            }

            $this->createHeats($stage, $plan['heats']);

            $stage->update(['status' => StageStatus::Drawn->value]);
            $locked->update(['status' => CompetitionStatus::Drawn->value]);

            self::logActivity('competition.drawn', $locked, $locked->event_id, [
                'stage_id' => $stage->id,
                'method' => $plan['method'],
                'seed' => $plan['seed'],
                'count' => count($plan['order']),
                'heats' => count($plan['heats']),
            ]);

            return $stage->refresh();
        });
    }

    /**
     * بازنشانی قرعه و سری‌ها؛ فقط قبل از اولین نتیجه.
     *
     * @throws InvalidArgumentException
     */
    public function reset(Competition $competition, User $actor): void
    {
        $this->competitions->assertWritable($competition);
        $this->assertRanked($competition);

        if ($this->hasRecordedResults($competition)) {
            throw new InvalidArgumentException(__('ranked.errors.locked_by_results'));
        }

        DB::transaction(function () use ($competition): void {
            foreach ($competition->stages as $stage) {
                $this->deleteHeats($stage);
                $stage->entries()->delete();
                $stage->draws()->delete();
                $stage->update(['status' => StageStatus::Pending->value]);
            }

            $competition->update(['status' => CompetitionStatus::Registration->value]);
        });

        self::logActivity('competition.draw_reset', $competition, $competition->event_id, []);
    }

    /**
     * drawn → ready.
     *
     * @throws InvalidArgumentException
     */
    public function markReady(Competition $competition, User $actor): Competition
    {
        $this->competitions->assertWritable($competition);
        $this->assertRanked($competition);

        if ($competition->status !== CompetitionStatus::Drawn) {
            throw new InvalidArgumentException(__('competition.errors.not_drawn'));
        }

        $first = $competition->firstStage();
        if ($first === null || ! Heat::query()->where('stage_id', $first->id)->exists()) {
            throw new InvalidArgumentException(__('ranked.errors.no_heats'));
        }

        $competition->update(['status' => CompetitionStatus::Ready->value]);

        self::logActivity('competition.status_changed', $competition, $competition->event_id, [
            'to' => CompetitionStatus::Ready->value,
        ]);

        return $competition->refresh();
    }

    /**
     * تنظیم مرحله‌ی مقدماتی (اندازه‌ی سری و تعداد صعودکننده)؛ فقط قبل از قرعه.
     *
     * @param  array<string, mixed>  $data  heat_size?, advance_per_heat?, advance_best_remaining?
     *
     * @throws InvalidArgumentException
     */
    public function configureStage(Stage $stage, User $actor, array $data): Stage
    {
        $competition = $stage->competition;
        $this->competitions->assertWritable($competition);
        $this->assertRanked($competition);

        if ($competition->status !== CompetitionStatus::Registration || $stage->is_final) {
            throw new InvalidArgumentException(__('ranked.errors.stage_locked'));
        }

        $config = $stage->config ?? [];

        foreach (['heat_size' => [2, self::MAX_HEAT_SIZE], 'advance_per_heat' => [0, self::MAX_HEAT_SIZE], 'advance_best_remaining' => [0, 256]] as $key => [$min, $max]) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            $value = filter_var($data[$key], FILTER_VALIDATE_INT);
            if ($value === false || $value < $min || $value > $max) {
                throw new InvalidArgumentException(__('ranked.errors.config_invalid', ['min' => $min, 'max' => $max]));
            }

            $config[$key] = $value;
        }

        if ((int) ($config['advance_per_heat'] ?? 0) + (int) ($config['advance_best_remaining'] ?? 0) < 1) {
            throw new InvalidArgumentException(__('ranked.errors.no_advancers'));
        }

        $stage->update(['config' => $config]);

        self::logActivity('competition.updated', $competition, $competition->event_id, ['changes' => ['stage_config']]);

        return $stage->refresh();
    }

    // ----------------------------------------------------------------- نتیجه

    /**
     * ثبت (یا ویرایش) نتیجه‌ی کل یک سری. هر ردیف: [competitor_id => ['value' => ..., 'status' => ok|DNS|DNF|DQ]].
     * شرکت‌کننده‌ی «ok» باید مقدار داشته باشد. رتبه‌ی سری را کد حساب می‌کند.
     *
     * @param  array<int|string, array{value?: mixed, status?: mixed}>  $rows
     *
     * @throws InvalidArgumentException
     */
    public function enterResults(Heat $heat, User $actor, array $rows, bool $canConfirm): Heat
    {
        $stage = $heat->stage;
        $competition = $stage->competition;

        $this->assertRanked($competition);
        $this->assertResultsOpen($competition);
        $this->assertNoLaterStage($stage);

        if ($heat->isConfirmed() && ! $canConfirm) {
            throw new InvalidArgumentException(__('competition.errors.confirmed_locked'));
        }

        $existing = HeatResult::query()->where('heat_id', $heat->id)->with('competitor')->get()->keyBy('competitor_id');

        foreach (array_keys($rows) as $key) {
            if (! $existing->has((int) $key)) {
                throw new InvalidArgumentException(__('ranked.errors.competitor_not_in_heat'));
            }
        }

        $prepared = [];
        foreach ($existing as $competitorId => $row) {
            $input = $rows[$competitorId] ?? $rows[(string) $competitorId] ?? [];

            if ($row->competitor->status === CompetitorStatus::Withdrawn) {
                $prepared[$competitorId] = ['status' => HeatResultStatus::Dns, 'value' => null];

                continue;
            }

            $status = HeatResultStatus::tryFrom((string) ($input['status'] ?? 'ok'))
                ?? throw new InvalidArgumentException(__('competition.errors.invalid_value'));

            $value = $status->isRanked() ? HeatValue::parse($input['value'] ?? null) : null;

            if ($status->isRanked() && $value === null) {
                throw new InvalidArgumentException(__('ranked.errors.value_required', ['name' => $row->competitor->name]));
            }

            $prepared[$competitorId] = ['status' => $status, 'value' => $value];
        }

        $ranks = $this->ranker->rank(
            collect($prepared)->map(fn (array $p, int $id): array => [
                'id' => $id,
                'value' => $p['value'],
                'status' => $p['status']->value,
            ])->values()->all(),
            $this->better($competition),
        );

        $confirmed = $canConfirm || ! $competition->requires_confirmation;

        DB::transaction(function () use ($heat, $existing, $prepared, $ranks, $confirmed, $actor): void {
            foreach ($existing as $competitorId => $row) {
                $row->update([
                    'status' => $prepared[$competitorId]['status']->value,
                    'value' => $prepared[$competitorId]['value'],
                    'rank' => $ranks[$competitorId]['rank'] ?? null,
                    'advanced' => false,
                ]);
            }

            $heat->update([
                'result_status' => ($confirmed ? ResultStatus::Confirmed : ResultStatus::Entered)->value,
                'entered_by' => $actor->id,
                'confirmed_by' => $confirmed ? $actor->id : null,
            ]);
        });

        self::logActivity('heat.result_entered', $heat, $competition->event_id, [
            'name' => $heat->name,
            'confirmed' => $confirmed,
        ]);

        $this->refreshState($competition);

        return $heat->refresh();
    }

    /**
     * تأیید نتیجه‌ی واردشده‌ی یک سری (فقط سطح تأیید؛ در Livewire با `confirmResult`).
     *
     * @throws InvalidArgumentException
     */
    public function confirmHeat(Heat $heat, User $actor): Heat
    {
        $competition = $heat->stage->competition;

        $this->assertRanked($competition);
        $this->assertResultsOpen($competition);

        if ($heat->result_status !== ResultStatus::Entered) {
            throw new InvalidArgumentException(__('competition.errors.nothing_to_confirm'));
        }

        $heat->update(['result_status' => ResultStatus::Confirmed->value, 'confirmed_by' => $actor->id]);

        self::logActivity('heat.result_confirmed', $heat, $competition->event_id, ['name' => $heat->name]);

        $this->refreshState($competition);

        return $heat->refresh();
    }

    /**
     * حذف نتیجه‌ی یک سری (فقط سطح تأیید).
     *
     * @throws InvalidArgumentException
     */
    public function clearHeat(Heat $heat, User $actor): Heat
    {
        $stage = $heat->stage;
        $competition = $stage->competition;

        $this->assertRanked($competition);
        $this->assertResultsOpen($competition);
        $this->assertNoLaterStage($stage);

        if ($heat->result_status === null) {
            throw new InvalidArgumentException(__('competition.errors.no_result'));
        }

        DB::transaction(function () use ($heat): void {
            HeatResult::query()->where('heat_id', $heat->id)->update([
                'value' => null, 'rank' => null, 'status' => HeatResultStatus::Ok->value, 'advanced' => false,
            ]);
            $heat->update(['result_status' => null, 'entered_by' => null, 'confirmed_by' => null]);
        });

        self::logActivity('heat.result_cleared', $heat, $competition->event_id, ['name' => $heat->name]);

        $this->refreshState($competition);

        return $heat->refresh();
    }

    // ----------------------------------------------------------------- صعود

    /**
     * ساخت مرحله‌ی بعد از روی نتیجه‌ی تأییدشده‌ی مرحله‌ی فعلی: «n نفر اول هر سری + بهترین‌های باقی‌مانده».
     *
     * @return array{stage: Stage, advancers: int, tied_at_cutoff: bool}
     *
     * @throws InvalidArgumentException
     */
    public function advance(Competition $competition, User $actor): array
    {
        $this->competitions->assertWritable($competition);
        $this->assertRanked($competition);

        if ($competition->status !== CompetitionStatus::Running) {
            throw new InvalidArgumentException(__('ranked.errors.not_running'));
        }

        $stages = $competition->stages()->get();
        $current = $stages->filter(fn (Stage $s): bool => Heat::query()->where('stage_id', $s->id)->exists())->last();

        if ($current === null || $current->is_final) {
            throw new InvalidArgumentException(__('ranked.errors.no_next_stage'));
        }

        $next = $stages->first(fn (Stage $s): bool => $s->position > $current->position);
        if ($next === null) {
            throw new InvalidArgumentException(__('ranked.errors.no_next_stage'));
        }

        $heats = Heat::query()->where('stage_id', $current->id)->orderBy('position')->get();
        if ($heats->contains(fn (Heat $h): bool => ! $h->isConfirmed())) {
            throw new InvalidArgumentException(__('ranked.errors.heats_unconfirmed'));
        }

        $selection = $this->advancer->select(
            $heats->map(fn (Heat $h): array => HeatResult::query()->where('heat_id', $h->id)->get()
                ->map(fn (HeatResult $r): array => [
                    'id' => $r->competitor_id,
                    'value' => $r->value,
                    'status' => $r->status->value,
                ])->all())->all(),
            (int) $current->config('advance_per_heat', 2),
            (int) $current->config('advance_best_remaining', 0),
            $this->better($competition),
        );

        if ($selection['advancers'] === []) {
            throw new InvalidArgumentException(__('ranked.errors.no_advancers'));
        }

        DB::transaction(function () use ($next, $heats, $selection): void {
            $order = $selection['advancers'];

            foreach ($order as $i => $competitorId) {
                $next->entries()->create([
                    'competitor_id' => $competitorId,
                    'seed' => $i + 1,
                    'draw_position' => $i + 1,
                ]);
            }

            $this->createHeats($next, $this->distribute($order, $this->heatCount($next, count($order))));

            HeatResult::query()
                ->whereIn('heat_id', $heats->pluck('id'))
                ->whereIn('competitor_id', $order)
                ->update(['advanced' => true]);
        });

        self::logActivity('competition.stage_advanced', $competition, $competition->event_id, [
            'from_stage_id' => $current->id,
            'to_stage_id' => $next->id,
            'advancers' => count($selection['advancers']),
            'tied_at_cutoff' => $selection['tied_at_cutoff'],
        ]);

        $this->refreshState($competition);

        return [
            'stage' => $next->refresh(),
            'advancers' => count($selection['advancers']),
            'tied_at_cutoff' => $selection['tied_at_cutoff'],
        ];
    }

    /**
     * برگرداندن آخرین مرحله‌ی ساخته‌شده (فقط اگر هنوز نتیجه‌ای در آن ثبت نشده).
     *
     * @throws InvalidArgumentException
     */
    public function rollbackAdvance(Competition $competition, User $actor): void
    {
        $this->competitions->assertWritable($competition);
        $this->assertRanked($competition);

        if ($competition->status === CompetitionStatus::Finished) {
            throw new InvalidArgumentException(__('competition.errors.competition_finished'));
        }

        $stages = $competition->stages()->get()
            ->filter(fn (Stage $s): bool => Heat::query()->where('stage_id', $s->id)->exists())
            ->values();

        if ($stages->count() < 2) {
            throw new InvalidArgumentException(__('ranked.errors.nothing_to_rollback'));
        }

        /** @var Stage $last */
        $last = $stages->last();
        /** @var Stage $previous */
        $previous = $stages[$stages->count() - 2];

        if (Heat::query()->where('stage_id', $last->id)->whereNotNull('result_status')->exists()) {
            throw new InvalidArgumentException(__('ranked.errors.next_stage_has_results'));
        }

        DB::transaction(function () use ($last, $previous): void {
            $this->deleteHeats($last);
            $last->entries()->delete();
            $last->update(['status' => StageStatus::Pending->value]);

            HeatResult::query()
                ->whereIn('heat_id', Heat::query()->where('stage_id', $previous->id)->select('id'))
                ->update(['advanced' => false]);
        });

        self::logActivity('competition.stage_rolled_back', $competition, $competition->event_id, ['stage_id' => $last->id]);

        $this->refreshState($competition);
    }

    /**
     * بازگشایی ورزش پایان‌یافته (مدیر): رتبه‌های نهایی پاک و ویرایش نتیجه‌ی فینال دوباره ممکن می‌شود.
     *
     * @throws InvalidArgumentException
     */
    public function reopen(Competition $competition, User $actor): Competition
    {
        $this->competitions->assertWritable($competition);
        $this->assertRanked($competition);

        if ($competition->status !== CompetitionStatus::Finished) {
            throw new InvalidArgumentException(__('competition.errors.not_finished'));
        }

        DB::transaction(function () use ($competition): void {
            foreach ($competition->stages as $stage) {
                $stage->entries()->update(['final_rank' => null]);
            }
            $competition->competitors()->update(['final_rank' => null]);
            $competition->update(['status' => CompetitionStatus::Running->value]);
        });

        self::logActivity('competition.reopened', $competition, $competition->event_id, []);

        return $competition->refresh();
    }

    // -------------------------------------------------------------- وضعیت‌ها

    /**
     * وضعیت مرحله‌ها و ورزش را از روی سری‌ها هم‌تراز می‌کند؛ با تأیید آخرین سری فینال ورزش پایان می‌یابد.
     */
    public function refreshState(Competition $competition): void
    {
        $competition->refresh();

        if ($competition->result_type !== 'ranked') {
            return;
        }

        $stages = $competition->stages()->get();
        $anyResult = false;
        $stagesWithHeats = 0;

        foreach ($stages as $stage) {
            $heats = Heat::query()->where('stage_id', $stage->id)->get(['id', 'result_status']);
            if ($heats->isEmpty()) {
                continue;
            }

            $stagesWithHeats++;
            $started = $heats->contains(fn (Heat $h): bool => $h->result_status !== null);
            $anyResult = $anyResult || $started;

            $status = match (true) {
                $heats->every(fn (Heat $h): bool => $h->result_status === ResultStatus::Confirmed) => StageStatus::Finished,
                $started => StageStatus::Running,
                default => StageStatus::Drawn,
            };

            if ($stage->status !== $status) {
                $stage->update(['status' => $status->value]);
            }
        }

        if ($anyResult && $competition->status === CompetitionStatus::Ready) {
            $competition->update(['status' => CompetitionStatus::Running->value]);
        } elseif (! $anyResult && $stagesWithHeats <= 1 && $competition->status === CompetitionStatus::Running) {
            $competition->update(['status' => CompetitionStatus::Ready->value]);
        }

        $competition->refresh();
        $final = $stages->first(fn (Stage $s): bool => $s->is_final);

        if ($final !== null && $competition->status === CompetitionStatus::Running) {
            $finalHeats = Heat::query()->where('stage_id', $final->id)->get(['id', 'result_status']);

            if ($finalHeats->isNotEmpty() && $finalHeats->every(fn (Heat $h): bool => $h->result_status === ResultStatus::Confirmed)) {
                $this->finish($competition, $final);
            }
        }
    }

    /**
     * رتبه‌ی نهایی هر شرکت‌کننده: فینالیست‌ها به ترتیب رتبه‌ی فینال، سپس حذف‌شدگان هر مرحله با نتیجه‌ی
     * همان مرحله (بعد از همه‌ی مرحله‌های بعدی). DNS/DNF/DQ و بدون نتیجه = null. تساوی = رتبه‌ی مشترک.
     *
     * @return array<int, int|null> competitor_id ← رتبه
     */
    public function finalRanks(Competition $competition): array
    {
        $better = $this->better($competition);
        $result = [];
        $assigned = [];
        $ranked = 0;

        foreach ($competition->stages()->get()->sortByDesc('position') as $stage) {
            $heatIds = Heat::query()->where('stage_id', $stage->id)->pluck('id');
            if ($heatIds->isEmpty()) {
                continue;
            }

            $pending = HeatResult::query()->whereIn('heat_id', $heatIds)->get()
                ->reject(fn (HeatResult $r): bool => isset($assigned[$r->competitor_id]))
                ->values();

            if ($pending->isEmpty()) {
                continue;
            }

            $ranks = $this->ranker->rank(
                $pending->map(fn (HeatResult $r): array => [
                    'id' => $r->competitor_id,
                    'value' => $r->value,
                    'status' => $r->status->value,
                ])->all(),
                $better,
            );

            $localRanked = 0;
            foreach ($pending as $r) {
                $rank = $ranks[$r->competitor_id]['rank'] ?? null;
                $result[$r->competitor_id] = $rank === null ? null : $ranked + $rank;
                $assigned[$r->competitor_id] = true;

                if ($rank !== null) {
                    $localRanked++;
                }
            }

            $ranked += $localRanked;
        }

        return $result;
    }

    // ------------------------------------------------------------- خواندن

    public function hasRecordedResults(Competition $competition): bool
    {
        return Heat::query()
            ->whereIn('stage_id', $competition->stages()->pluck('id'))
            ->whereNotNull('result_status')
            ->exists();
    }

    /**
     * داده‌ی نمایش: مرحله‌ها به‌همراه سری‌ها (با نتیجه‌ها و شرکت‌کننده‌ها)؛ فقط مرحله‌هایی که سری دارند.
     *
     * @return list<array{stage: Stage, heats: Collection<int, Heat>}>
     */
    public function board(Competition $competition): array
    {
        $stages = $competition->stages()->get();

        $heats = Heat::query()
            ->whereIn('stage_id', $stages->pluck('id'))
            ->with('results.competitor')
            ->orderBy('position')
            ->get()
            ->groupBy('stage_id');

        $board = [];
        foreach ($stages as $stage) {
            $stageHeats = $heats->get($stage->id);

            if ($stageHeats !== null && $stageHeats->isNotEmpty()) {
                $board[] = ['stage' => $stage, 'heats' => $stageHeats->values()];
            }
        }

        return $board;
    }

    public function better(Competition $competition): string
    {
        $better = (string) $competition->config('better', 'lower');

        return in_array($better, HeatRanker::BETTER, true) ? $better : 'lower';
    }

    public function valueKind(Competition $competition): string
    {
        return (string) $competition->config('value_kind', 'time');
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @throws InvalidArgumentException
     */
    private function assertRanked(Competition $competition): void
    {
        if ($competition->result_type !== 'ranked') {
            throw new InvalidArgumentException(__('ranked.errors.not_ranked'));
        }
    }

    private function assertResultsOpen(Competition $competition): void
    {
        $this->competitions->assertWritable($competition);

        if ($competition->status === CompetitionStatus::Finished) {
            throw new InvalidArgumentException(__('competition.errors.competition_finished'));
        }

        if (! $competition->status->acceptsResults()) {
            throw new InvalidArgumentException(__('competition.errors.results_closed'));
        }
    }

    /** ویرایش/حذف نتیجه‌ی مرحله‌ای که مرحله‌ی بعدش ساخته شده، مسدود است. */
    private function assertNoLaterStage(Stage $stage): void
    {
        $later = Stage::query()
            ->where('competition_id', $stage->competition_id)
            ->where('position', '>', $stage->position)
            ->pluck('id');

        if ($later->isNotEmpty() && Heat::query()->whereIn('stage_id', $later)->exists()) {
            throw new InvalidArgumentException(__('ranked.errors.locked_by_next_stage'));
        }
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

    /**
     * @return Collection<int, Competitor>
     */
    private function eligible(Competition $competition): Collection
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

    /** مرحله‌ی نهایی همیشه یک سری است؛ بقیه بر اساس `heat_size`. */
    private function heatCount(Stage $stage, int $competitors): int
    {
        if ($stage->is_final) {
            return 1;
        }

        $size = max(2, min(self::MAX_HEAT_SIZE, (int) $stage->config('heat_size', self::DEFAULT_HEAT_SIZE)));

        return max(1, (int) ceil($competitors / $size));
    }

    /**
     * پخش «مارپیچ» (snake) ترتیب قرعه/عملکرد بین سری‌ها تا سری‌ها متوازن شوند.
     *
     * @param  list<int>  $order
     * @return list<list<int>>
     */
    private function distribute(array $order, int $count): array
    {
        $heats = array_fill(0, $count, []);

        foreach ($order as $i => $id) {
            $row = intdiv($i, $count);
            $col = $i % $count;
            $heats[$row % 2 === 0 ? $col : $count - 1 - $col][] = $id;
        }

        return $heats;
    }

    /**
     * @param  list<list<int>>  $groups
     */
    private function createHeats(Stage $stage, array $groups): void
    {
        foreach ($groups as $i => $competitorIds) {
            $heat = Heat::query()->create([
                'stage_id' => $stage->id,
                'name' => $stage->is_final ? $stage->name : __('ranked.heat_name', ['n' => $i + 1]),
                'position' => $i + 1,
            ]);

            foreach (array_values($competitorIds) as $lane => $competitorId) {
                HeatResult::query()->create([
                    'heat_id' => $heat->id,
                    'competitor_id' => $competitorId,
                    'lane' => $lane + 1,
                    'status' => HeatResultStatus::Ok->value,
                ]);
            }
        }
    }

    private function deleteHeats(Stage $stage): void
    {
        $ids = Heat::query()->where('stage_id', $stage->id)->pluck('id');

        HeatResult::query()->whereIn('heat_id', $ids)->delete();
        Heat::query()->whereIn('id', $ids)->delete();
    }

    private function finish(Competition $competition, Stage $final): void
    {
        DB::transaction(function () use ($competition, $final): void {
            $ranks = $this->finalRanks($competition);

            $competition->competitors()->update(['final_rank' => null]);

            foreach ($ranks as $competitorId => $rank) {
                Competitor::query()
                    ->where('competition_id', $competition->id)
                    ->whereKey($competitorId)
                    ->update(['final_rank' => $rank]);

                $final->entries()->where('competitor_id', $competitorId)->update(['final_rank' => $rank]);
            }

            $final->update(['status' => StageStatus::Finished->value]);
            $competition->update(['status' => CompetitionStatus::Finished->value]);
        });

        self::logActivity('competition.finished', $competition, $competition->event_id, []);
    }
}
