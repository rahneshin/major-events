<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MatchStatus;
use App\Enums\ResultStatus;
use App\Models\Stage;
use App\Services\Competitions\Engine\StandingsCalculator;

/**
 * Step 12B — رده‌بندی یک مرحله از روی بازی‌های تأییدشده‌ی در-رده‌بندی.
 * Step 12D — رده‌بندی هر گروه از مرحله‌ی گروهی (`forGroups`) و قرعه‌ی اجباری برای تعیین صعودکننده‌ها.
 */
class StandingsService
{
    public function __construct(private StandingsCalculator $calculator) {}

    /**
     * @param  string|null  $group  فقط یک گروه از مرحله‌ی گروهی
     * @param  bool  $forceLots  اگر معیار آخر «قرعه» در فهرست نبود اضافه شود تا تساوی باقی نماند (برای تعیین صعودکننده)
     * @return list<array<string, mixed>> هر ردیف: competitor_id, name, rank, tied, played, won, drawn, lost, for, against, diff, point_diff, points
     */
    public function forStage(Stage $stage, ?string $group = null, bool $forceLots = false): array
    {
        $competition = $stage->competition;

        $entries = $stage->entries()
            ->with('competitor')
            ->when($group !== null, fn ($q) => $q->where('group_name', $group))
            ->get();

        $matches = $stage->matches()
            ->whereNull('parent_match_id')
            ->where('counts_in_standings', true)
            ->when($group !== null, fn ($q) => $q->where('group_name', $group))
            ->where('result_status', ResultStatus::Confirmed->value)
            ->whereIn('status', [MatchStatus::Finished->value, MatchStatus::Walkover->value])
            ->whereNotNull('a_id')
            ->whereNotNull('b_id')
            ->get()
            ->map(fn ($m): array => [
                'a' => (int) $m->a_id,
                'b' => (int) $m->b_id,
                'score_a' => $m->score_a,
                'score_b' => $m->score_b,
                'points_a' => $m->points_a,
                'points_b' => $m->points_b,
                'winner' => $m->winner_id !== null ? (int) $m->winner_id : null,
                'walkover' => $m->status === MatchStatus::Walkover,
            ])
            ->all();

        $tiebreakers = (array) ($stage->config('tiebreakers') ?? $competition->config('tiebreakers', []));

        if ($forceLots && ! in_array('draw_lots', $tiebreakers, true)) {
            $tiebreakers[] = 'draw_lots';
        }

        $config = [
            'table_points' => (array) $competition->config('table_points', []),
            'tiebreakers' => $tiebreakers,
            'lots_seed' => $stage->config('lots_seed'),
        ];

        $names = $entries->mapWithKeys(fn ($e) => [(int) $e->competitor_id => $e->competitor->name]);

        $rows = $this->calculator->calculate($entries->pluck('competitor_id')->map(fn ($i) => (int) $i)->all(), $matches, $config);

        return array_map(fn (array $row): array => $row + ['name' => $names[$row['competitor_id']] ?? '—'], $rows);
    }

    /**
     * رده‌بندی جدا برای هر گروه.
     *
     * @return array<string, list<array<string, mixed>>> نام گروه (A, B, …) ← ردیف‌ها
     */
    public function forGroups(Stage $stage, bool $forceLots = false): array
    {
        // reorder: رابطه‌ی entries خودش بر draw_position مرتب است و با DISTINCT در PostgreSQL ناسازگار است
        $names = $stage->entries()
            ->reorder()
            ->whereNotNull('group_name')
            ->distinct()
            ->orderBy('group_name')
            ->pluck('group_name')
            ->map(fn ($name): string => (string) $name)
            ->all();

        $tables = [];

        foreach ($names as $name) {
            $tables[$name] = $this->forStage($stage, $name, $forceLots);
        }

        return $tables;
    }
}
