<?php

declare(strict_types=1);

namespace App\Services\Competitions\Engine;

/**
 * صعود از سری‌ها (PHP خالص): «n نفر اول هر سری + بهترین‌های باقی‌مانده».
 *
 *  - «n نفر اول» بر اساس رتبه است (رتبه ≤ n)؛ مساوی‌ها در مرز همه صعود می‌کنند.
 *  - «بهترین‌های باقی‌مانده» از بین بقیه‌ی رتبه‌دارها با مقدار؛ اگر در مرز تساوی باشد همه‌ی مساوی‌ها
 *    صعود می‌کنند و `tied_at_cutoff=true` گزارش می‌شود (پنهان نمی‌شود).
 *  - DNS/DNF/DQ و «بدون مقدار» هرگز صعود نمی‌کنند.
 */
final class HeatAdvancer
{
    /**
     * @param  list<list<array{id: int, value?: int|float|string|null, status?: string}>>  $heats
     * @return array{advancers: list<int>, by_rank: list<int>, by_time: list<int>, tied_at_cutoff: bool}
     */
    public function select(array $heats, int $perHeat, int $bestRemaining, string $better = 'lower'): array
    {
        $ranker = new HeatRanker;
        HeatRanker::assertBetter($better);

        /** @var array<int, int> $milli */
        $milli = [];
        /** @var list<int> $byRank */
        $byRank = [];
        /** @var list<int> $pool */
        $pool = [];

        foreach ($heats as $heat) {
            $ranks = $ranker->rank($heat, $better);

            foreach ($heat as $entry) {
                $id = (int) $entry['id'];
                $rank = $ranks[$id]['rank'] ?? null;

                if ($rank === null) {
                    continue;
                }

                $milli[$id] = (int) HeatRanker::milli($entry['value'] ?? null);

                if ($perHeat > 0 && $rank <= $perHeat) {
                    $byRank[] = $id;
                } else {
                    $pool[] = $id;
                }
            }
        }

        $compare = function (int $x, int $y) use ($milli, $better): int {
            $cmp = $better === 'lower' ? $milli[$x] <=> $milli[$y] : $milli[$y] <=> $milli[$x];

            return $cmp !== 0 ? $cmp : $x <=> $y;
        };

        usort($pool, $compare);

        $byTime = [];
        $tiedAtCutoff = false;

        if ($bestRemaining > 0 && $pool !== []) {
            $take = min($bestRemaining, count($pool));
            $cutoff = $milli[$pool[$take - 1]];

            foreach ($pool as $id) {
                $inside = $better === 'lower' ? $milli[$id] <= $cutoff : $milli[$id] >= $cutoff;
                if ($inside) {
                    $byTime[] = $id;
                }
            }

            $tiedAtCutoff = count($byTime) > $take;
        }

        $advancers = array_merge($byRank, $byTime);
        usort($advancers, $compare);

        return [
            'advancers' => array_values($advancers),
            'by_rank' => $byRank,
            'by_time' => $byTime,
            'tied_at_cutoff' => $tiedAtCutoff,
        ];
    }
}
