<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MatchStatus;
use App\Enums\ResultStatus;
use App\Models\Stage;
use App\Services\Competitions\Engine\StandingsCalculator;

/**
 * Step 12B — رده‌بندی یک مرحله از روی بازی‌های تأییدشده‌ی در-رده‌بندی.
 */
class StandingsService
{
    public function __construct(private StandingsCalculator $calculator) {}

    /**
     * @return list<array<string, mixed>> هر ردیف: competitor_id, name, rank, tied, played, won, drawn, lost, for, against, diff, point_diff, points
     */
    public function forStage(Stage $stage): array
    {
        $competition = $stage->competition;
        $entries = $stage->entries()->with('competitor')->get();

        $matches = $stage->matches()
            ->whereNull('parent_match_id')
            ->where('counts_in_standings', true)
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

        $config = [
            'table_points' => (array) $competition->config('table_points', []),
            'tiebreakers' => (array) ($stage->config('tiebreakers') ?? $competition->config('tiebreakers', [])),
            'lots_seed' => $stage->config('lots_seed'),
        ];

        $names = $entries->mapWithKeys(fn ($e) => [(int) $e->competitor_id => $e->competitor->name]);

        $rows = $this->calculator->calculate($entries->pluck('competitor_id')->map(fn ($i) => (int) $i)->all(), $matches, $config);

        return array_map(fn (array $row): array => $row + ['name' => $names[$row['competitor_id']] ?? '—'], $rows);
    }
}
