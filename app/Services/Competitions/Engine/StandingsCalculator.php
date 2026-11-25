<?php

declare(strict_types=1);

namespace App\Services\Competitions\Engine;

/**
 * رده‌بندی لیگ از روی بازی‌های تأییدشده.
 *
 * معیارهای قابل‌تنظیم (به ترتیب، همه «بیشتر = بهتر»):
 *   points | wins | diff (goal_diff/game_diff/set_diff) | for (goals_for/games_for) | point_diff
 *   head_to_head_points | head_to_head_diff | draw_lots
 *
 * رودررو: فقط بازی‌های بین اعضای گروه مساوی حساب می‌شود؛ اگر گروه کوچک‌تر شد، دوباره روی زیرگروه
 * اجرا می‌شود، وگرنه معیار بعدی. draw_lots فقط با `lots_seed` تعیین‌کننده است؛ بدون آن، تساوی می‌ماند.
 * خروجی مستقل از ترتیب ورودی است.
 */
final class StandingsCalculator
{
    private const ALIASES = [
        'goal_diff' => 'diff', 'game_diff' => 'diff', 'set_diff' => 'diff', 'wins' => 'won',
        'goals_for' => 'for', 'games_for' => 'for', 'sets_for' => 'for',
    ];

    /**
     * @param  list<int>  $ids
     * @param  list<array{a: int, b: int, score_a?: int|null, score_b?: int|null, points_a?: int|null, points_b?: int|null, winner?: int|null, walkover?: bool}>  $matches
     * @param  array{table_points?: array<string, mixed>, tiebreakers?: list<string>, lots_seed?: string|null}  $config
     * @return list<array{competitor_id: int, rank: int, tied: bool, played: int, won: int, drawn: int, lost: int, for: int, against: int, diff: int, points_for: int, points_against: int, point_diff: int, points: int}>
     */
    public function calculate(array $ids, array $matches, array $config = []): array
    {
        $ids = array_values(array_unique($ids));
        sort($ids);

        $matches = array_values(array_filter(
            $matches,
            fn (array $m): bool => in_array($m['a'], $ids, true) && in_array($m['b'], $ids, true) && $m['a'] !== $m['b'],
        ));

        $rows = $this->rows($ids, $matches, $config);

        $criteria = array_map(
            fn (string $c): string => self::ALIASES[$c] ?? $c,
            array_values($config['tiebreakers'] ?? ['points', 'head_to_head_points', 'diff', 'for', 'draw_lots']),
        );

        $groups = $this->split($ids, $criteria, $rows, $matches, $config);

        $result = [];
        $position = 1;

        foreach ($groups as $group) {
            foreach ($group as $id) {
                $result[] = ['competitor_id' => $id, 'rank' => $position, 'tied' => count($group) > 1] + $rows[$id];
            }
            $position += count($group);
        }

        return $result;
    }

    /**
     * @param  list<int>  $ids
     * @param  list<array<string, mixed>>  $matches
     * @param  array<string, mixed>  $config
     * @return array<int, array{played: int, won: int, drawn: int, lost: int, for: int, against: int, diff: int, points_for: int, points_against: int, point_diff: int, points: int}>
     */
    private function rows(array $ids, array $matches, array $config): array
    {
        $table = $config['table_points'] ?? [];
        $win = (int) ($table['win'] ?? 3);
        $draw = (int) ($table['draw'] ?? 1);
        $loss = (int) ($table['loss'] ?? 0);
        $byScore = (array) ($table['by_score'] ?? []);

        $rows = [];
        foreach ($ids as $id) {
            $rows[$id] = [
                'played' => 0, 'won' => 0, 'drawn' => 0, 'lost' => 0, 'for' => 0, 'against' => 0, 'diff' => 0,
                'points_for' => 0, 'points_against' => 0, 'point_diff' => 0, 'points' => 0,
            ];
        }

        foreach ($matches as $m) {
            $a = (int) $m['a'];
            $b = (int) $m['b'];
            $sa = (int) ($m['score_a'] ?? 0);
            $sb = (int) ($m['score_b'] ?? 0);
            $walkover = (bool) ($m['walkover'] ?? false);

            $rows[$a]['played']++;
            $rows[$b]['played']++;

            if ($walkover) {
                $winner = $m['winner'] ?? null;
                if ($winner !== $a && $winner !== $b) {
                    continue;
                }
                $loser = $winner === $a ? $b : $a;
                $rows[$winner]['won']++;
                $rows[$winner]['points'] += $win;
                $rows[$loser]['lost']++;
                $rows[$loser]['points'] += $loss;

                continue;
            }

            $rows[$a]['for'] += $sa;
            $rows[$a]['against'] += $sb;
            $rows[$b]['for'] += $sb;
            $rows[$b]['against'] += $sa;
            $rows[$a]['points_for'] += (int) ($m['points_a'] ?? 0);
            $rows[$a]['points_against'] += (int) ($m['points_b'] ?? 0);
            $rows[$b]['points_for'] += (int) ($m['points_b'] ?? 0);
            $rows[$b]['points_against'] += (int) ($m['points_a'] ?? 0);

            if ($sa === $sb) {
                $rows[$a]['drawn']++;
                $rows[$b]['drawn']++;
                $rows[$a]['points'] += $draw;
                $rows[$b]['points'] += $draw;

                continue;
            }

            $aWins = $sa > $sb;
            $w = $aWins ? $a : $b;
            $l = $aWins ? $b : $a;
            $key = max($sa, $sb).'-'.min($sa, $sb);
            [$wp, $lp] = isset($byScore[$key]) ? [(int) $byScore[$key][0], (int) $byScore[$key][1]] : [$win, $loss];

            $rows[$w]['won']++;
            $rows[$l]['lost']++;
            $rows[$w]['points'] += $wp;
            $rows[$l]['points'] += $lp;
        }

        foreach ($rows as $id => $row) {
            $rows[$id]['diff'] = $row['for'] - $row['against'];
            $rows[$id]['point_diff'] = $row['points_for'] - $row['points_against'];
        }

        return $rows;
    }

    /**
     * @param  list<int>  $ids
     * @param  list<string>  $criteria
     * @param  array<int, array<string, int>>  $rows
     * @param  list<array<string, mixed>>  $matches
     * @param  array<string, mixed>  $config
     * @return list<list<int>> گروه‌های هم‌رتبه به ترتیب از بهترین
     */
    private function split(array $ids, array $criteria, array $rows, array $matches, array $config): array
    {
        sort($ids);

        if (count($ids) <= 1) {
            return [$ids];
        }

        if ($criteria === []) {
            return [$ids];
        }

        $criterion = $criteria[0];
        $rest = array_slice($criteria, 1);

        if ($criterion === 'draw_lots') {
            $seed = $config['lots_seed'] ?? null;

            if (! is_string($seed) || $seed === '') {
                return $this->split($ids, $rest, $rows, $matches, $config);
            }

            $shuffled = (new SeededRandom($seed.':'.implode(',', $ids)))->shuffle($ids);

            return array_map(fn (int $id): array => [$id], $shuffled);
        }

        if (str_starts_with($criterion, 'head_to_head_')) {
            $mini = $this->rows($ids, array_values(array_filter(
                $matches,
                fn (array $m): bool => in_array($m['a'], $ids, true) && in_array($m['b'], $ids, true),
            )), $config);

            $field = $criterion === 'head_to_head_points' ? 'points' : 'diff';
            $parts = $this->partition($ids, fn (int $id): int => $mini[$id][$field]);

            if (count($parts) === 1) {
                return $this->split($ids, $rest, $rows, $matches, $config);
            }

            // گروه کوچک‌تر شد ← رودررو دوباره روی هر زیرگروه
            return $this->flatten($parts, fn (array $part): array => $this->split($part, $criteria, $rows, $matches, $config));
        }

        if (! isset($rows[$ids[0]][$criterion])) {
            return $this->split($ids, $rest, $rows, $matches, $config);
        }

        $parts = $this->partition($ids, fn (int $id): int => $rows[$id][$criterion]);

        return $this->flatten($parts, fn (array $part): array => $this->split($part, $rest, $rows, $matches, $config));
    }

    /**
     * @param  list<int>  $ids
     * @return list<list<int>> بر اساس مقدار نزولی
     */
    private function partition(array $ids, callable $metric): array
    {
        $buckets = [];
        foreach ($ids as $id) {
            $buckets[$metric($id)][] = $id;
        }
        krsort($buckets);

        return array_values($buckets);
    }

    /**
     * @param  list<list<int>>  $parts
     * @return list<list<int>>
     */
    private function flatten(array $parts, callable $resolve): array
    {
        $out = [];
        foreach ($parts as $part) {
            foreach ($resolve($part) as $group) {
                $out[] = $group;
            }
        }

        return $out;
    }
}
