<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CompetitionStatus;
use App\Enums\CompetitorStatus;
use App\Enums\StageStatus;
use App\Models\Competition;
use App\Models\Stage;
use App\Models\User;
use App\Services\Competitions\Engine\DrawEngine;
use App\Services\Competitions\Engine\RoundRobinGenerator;
use App\Services\Competitions\Engine\SeededRandom;
use App\Support\LogsActivity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Step 12B — قرعه‌کشی و ساخت جدول بازی‌ها (فعلاً لیگ دوره‌ای؛ حذفی/گروهی در 12D).
 *
 * قرعه = «پیش‌نمایش» (بدون ذخیره، از روی seed قابل بازتولید) + «تأیید» (ذخیره‌ی سند و خانه‌ها).
 * ساخت جدول جدا از قرعه است. بعد از اولین نتیجه‌ی تأییدشده هر دو قفل‌اند؛ بازنشانی فقط قبل از آن.
 */
class DrawService
{
    use LogsActivity;

    public const MIN_COMPETITORS = 2;

    public function __construct(
        private CompetitionService $competitions,
        private DrawEngine $engine,
        private RoundRobinGenerator $roundRobin,
    ) {}

    /**
     * پیش‌نمایش قرعه؛ چیزی ذخیره نمی‌شود.
     *
     * @return array{method: string, seed: string, order: list<int>, names: array<int, string>}
     *
     * @throws InvalidArgumentException
     */
    public function preview(Competition $competition, string $method, ?string $seed = null): array
    {
        $stage = $this->drawableStage($competition);
        $competitors = $this->eligible($competition);

        $result = $this->engine->draw(
            $competitors->map(fn ($c) => ['id' => $c->id, 'seed' => $c->seed])->all(),
            $method,
            $seed,
        );

        $this->assertSupported($stage);

        return $result + ['names' => $competitors->pluck('name', 'id')->all()];
    }

    /**
     * تأیید قرعه با همان seed پیش‌نمایش (نتیجه بازتولید می‌شود، نه دوباره‌قرعه).
     *
     * @throws InvalidArgumentException
     */
    public function confirm(Competition $competition, User $actor, string $method, string $seed): Stage
    {
        $this->competitions->assertWritable($competition);

        return DB::transaction(function () use ($competition, $actor, $method, $seed): Stage {
            // قفل ردیف: دو تأیید هم‌زمان قرعه‌ی دوم را می‌سازند
            $locked = Competition::query()->whereKey($competition->id)->lockForUpdate()->firstOrFail();
            $stage = $this->drawableStage($locked);
            $this->assertSupported($stage);

            $result = $this->preview($locked, $method, $seed);

            $stage->draws()->create([
                'method' => $result['method'],
                'rng_seed' => $result['seed'],
                'constraints' => null,
                'result' => ['order' => $result['order']],
                'performed_by' => $actor->id,
            ]);

            $seeds = $locked->competitors()->whereIn('id', $result['order'])->pluck('seed', 'id');

            foreach ($result['order'] as $i => $competitorId) {
                $stage->entries()->create([
                    'competitor_id' => $competitorId,
                    'seed' => $seeds[$competitorId] ?? null,
                    'draw_position' => $i + 1,
                ]);
            }

            // seed «قرعه‌ی مساوی» رده‌بندی همین‌جا ثبت می‌شود تا تساوی‌ها قابل‌بازتولید حل شوند
            $stage->update([
                'status' => StageStatus::Drawn->value,
                'config' => ($stage->config ?? []) + ['lots_seed' => SeededRandom::newSeed()],
            ]);
            $locked->update(['status' => CompetitionStatus::Drawn->value]);

            self::logActivity('competition.drawn', $locked, $locked->event_id, [
                'stage_id' => $stage->id,
                'method' => $result['method'],
                'seed' => $result['seed'],
                'count' => count($result['order']),
            ]);

            return $stage->refresh();
        });
    }

    /**
     * ساخت بازی‌های مرحله‌ی قرعه‌شده (لیگ دوره‌ای). دسته‌ای و داخل تراکنش.
     *
     * @return int تعداد بازی‌های ساخته‌شده
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

        $ids = $stage->entries()->pluck('competitor_id')->map(fn ($id) => (int) $id)->all();
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

        DB::transaction(function () use ($rows): void {
            foreach (array_chunk($rows, 100) as $chunk) {
                DB::table('matches')->insert($chunk);
            }
        });

        self::logActivity('competition.fixtures_generated', $competition, $competition->event_id, [
            'stage_id' => $stage->id,
            'matches' => count($rows),
        ]);

        return count($rows);
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
                    'config' => collect($stage->config ?? [])->except('lots_seed')->all(),
                ]);
            }

            $competition->update(['status' => CompetitionStatus::Registration->value]);
        });

        self::logActivity('competition.draw_reset', $competition, $competition->event_id, []);
    }

    // ---------------------------------------------------------------- helpers

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
