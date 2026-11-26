<?php

declare(strict_types=1);

namespace App\Services\Competitions\Engine;

/**
 * جدول حذفی تک‌شکست (Step 12D): بذرگذاری، بای، بازی ردهبندی، رتبه‌ی نهایی و بذرگذاری صعودکننده‌های گروه‌ها.
 *
 * خروجی فقط داده است (بدون DB و Laravel)؛ ساخت ردیف بازی‌ها کار `BracketService` است.
 *
 *  - اندازه‌ی جدول = کوچک‌ترین توان ۲ که از تعداد شرکت‌کننده کمتر نباشد.
 *  - ترتیب بذرها با `seedOrder(8) = [1,8,4,5,2,7,3,6]` (بذر ۱ و ۲ فقط در فینال به هم می‌رسند).
 *  - بذرهای بزرگ‌تر از n وجود ندارند ← بای برای بذرهای بالا: آن بازی ساخته نمی‌شود و بذر مستقیم در دور بعد می‌نشیند.
 *  - شماره‌ی بازی‌های دور اول فقط بین بازی‌های واقعی پیوسته است (بدون سوراخ).
 *  - بازی ردهبندی (سوم/چهارم) فقط برای n ≥ 4 و هم‌دور فینال است (شماره‌ی ۲).
 */
final class BracketGenerator
{
    public const MAX_ENTRANTS = 256;

    public const BRACKET_WINNERS = 'winners';

    public const BRACKET_THIRD_PLACE = 'third_place';

    /**
     * کوچک‌ترین توان ۲ ≥ n (حداقل ۲).
     */
    public function sizeFor(int $entrants): int
    {
        $size = 2;

        while ($size < $entrants) {
            $size *= 2;
        }

        return $size;
    }

    /**
     * ترتیب بذرها در خانه‌های دور اول؛ هر دو عنصر مجاور یک بازی‌اند.
     *
     * @return list<int>
     */
    public function seedOrder(int $size): array
    {
        if ($size < 2 || ($size & ($size - 1)) !== 0) {
            throw new \InvalidArgumentException('Bracket size must be a power of two.');
        }

        $order = [1, 2];

        while (count($order) < $size) {
            $next = count($order) * 2;
            $expanded = [];

            foreach ($order as $seed) {
                $expanded[] = $seed;
                $expanded[] = $next + 1 - $seed;
            }

            $order = $expanded;
        }

        return $order;
    }

    /**
     * @return array{
     *     size: int,
     *     rounds: int,
     *     entrants: int,
     *     third_place: bool,
     *     byes: list<int>,
     *     matches: list<array{
     *         round: int,
     *         number: int,
     *         bracket: string,
     *         a: array<string, mixed>,
     *         b: array<string, mixed>,
     *         next: array{round: int, number: int, slot: string}|null,
     *         loser_next: array{round: int, number: int, slot: string}|null
     *     }>
     * } هر طرف بازی یکی از `{seed}` | `{winner_of: {round, number}}` | `{loser_of: {round, number}}` است
     */
    public function generate(int $entrants, bool $thirdPlace = false): array
    {
        if ($entrants < 2 || $entrants > self::MAX_ENTRANTS) {
            throw new \InvalidArgumentException('Entrants must be between 2 and '.self::MAX_ENTRANTS.'.');
        }

        $size = $this->sizeFor($entrants);
        $rounds = $this->roundsFor($size);
        $order = $this->seedOrder($size);
        $withThird = $thirdPlace && $entrants >= 4;

        // دور اول: خانه‌ی p = زوج (order[2p], order[2p+1])؛ بذر کوچک‌تر همیشه حاضر است
        $first = [];
        $number = 0;

        for ($p = 0; $p < intdiv($size, 2); $p++) {
            $seedA = $order[2 * $p];
            $seedB = $order[2 * $p + 1];

            $first[$p] = [
                'a' => $seedA,
                'b' => $seedB,
                'number' => $seedB <= $entrants ? ++$number : null,
            ];
        }

        $matches = [];
        $byes = [];

        foreach ($first as $p => $slot) {
            if ($slot['number'] === null) {
                $byes[] = $slot['a'];

                continue;
            }

            $matches[] = [
                'round' => 1,
                'number' => $slot['number'],
                'bracket' => self::BRACKET_WINNERS,
                'a' => ['seed' => $slot['a']],
                'b' => ['seed' => $slot['b']],
                'next' => $this->next(1, $p, $rounds),
                'loser_next' => null,
            ];
        }

        for ($round = 2; $round <= $rounds; $round++) {
            $count = intdiv($size, 2 ** $round);

            for ($q = 0; $q < $count; $q++) {
                $matches[] = [
                    'round' => $round,
                    'number' => $q + 1,
                    'bracket' => self::BRACKET_WINNERS,
                    'a' => $this->feeder($round - 1, 2 * $q, $first),
                    'b' => $this->feeder($round - 1, 2 * $q + 1, $first),
                    'next' => $this->next($round, $q, $rounds),
                    'loser_next' => null,
                ];
            }
        }

        if ($withThird) {
            foreach ($matches as $i => $match) {
                if ($match['round'] === $rounds - 1 && in_array($match['number'], [1, 2], true)) {
                    $matches[$i]['loser_next'] = [
                        'round' => $rounds,
                        'number' => 2,
                        'slot' => $match['number'] === 1 ? 'a' : 'b',
                    ];
                }
            }

            $matches[] = [
                'round' => $rounds,
                'number' => 2,
                'bracket' => self::BRACKET_THIRD_PLACE,
                'a' => ['loser_of' => ['round' => $rounds - 1, 'number' => 1]],
                'b' => ['loser_of' => ['round' => $rounds - 1, 'number' => 2]],
                'next' => null,
                'loser_next' => null,
            ];
        }

        usort($matches, fn (array $x, array $y): int => [$x['round'], $x['number']] <=> [$y['round'], $y['number']]);

        return [
            'size' => $size,
            'rounds' => $rounds,
            'entrants' => $entrants,
            'third_place' => $withThird,
            'byes' => $byes,
            'matches' => $matches,
        ];
    }

    /**
     * رتبه‌ی نهایی از نتیجه‌ی بازی‌های تمام‌شده.
     *
     *  - فینال: برنده ۱، بازنده ۲.
     *  - بازنده‌ی دور r: size / 2^r + 1 (نیمه‌نهایی ۳، یک‌چهارم ۵، یک‌هشتم ۹ …)؛ هم‌رتبه‌ها رتبه‌ی مشترک دارند.
     *  - اگر بازی ردهبندی هست: برنده ۳ و بازنده ۴ (جای رتبه‌ی مشترک نیمه‌نهایی را می‌گیرد).
     *
     * @param  list<array{round: int, bracket: string, winner: int, loser: int}>  $results
     * @return array<int, int> شناسه‌ی شرکت‌کننده ← رتبه
     */
    public function ranks(int $size, array $results): array
    {
        $rounds = $this->roundsFor($size);
        $ranks = [];
        $third = [];

        foreach ($results as $result) {
            if ($result['bracket'] === self::BRACKET_THIRD_PLACE) {
                $third[] = $result;

                continue;
            }

            if ($result['round'] === $rounds) {
                $ranks[$result['winner']] = 1;
                $ranks[$result['loser']] = 2;

                continue;
            }

            $ranks[$result['loser']] = intdiv($size, 2 ** $result['round']) + 1;
        }

        foreach ($third as $result) {
            $ranks[$result['winner']] = 3;
            $ranks[$result['loser']] = 4;
        }

        return $ranks;
    }

    /**
     * ترتیب بذر صعودکننده‌های گروه‌ها (اندیس ۰ = بذر ۱): اول‌ها، بعد دوم‌ها با جابه‌جایی جفت‌گروه‌ها
     * تا اول یک گروه با دوم گروه کناری‌اش (A1–B2، B1–A2، C1–D2، …) در دور اول روبه‌رو شود.
     * برای تعداد گروه فرد یا رتبه‌ی صعود بالاتر از ۲ «در حد امکان» است، نه تضمین.
     *
     * @return list<array{group: int, rank: int}> group = اندیس گروه (۰ = A)
     */
    public function qualifierSeeding(int $groups, int $perGroup): array
    {
        if ($groups < 2 || $perGroup < 1) {
            throw new \InvalidArgumentException('At least two groups and one qualifier per group are required.');
        }

        $order = [];

        for ($rank = 1; $rank <= $perGroup; $rank++) {
            $indexes = range(0, $groups - 1);

            if ($rank % 2 === 0) {
                $swapped = [];

                for ($i = 0; $i < $groups; $i += 2) {
                    if ($i + 1 < $groups) {
                        $swapped[] = $i + 1;
                        $swapped[] = $i;
                    } else {
                        $swapped[] = $i;
                    }
                }

                $indexes = array_reverse($swapped);
            }

            foreach ($indexes as $group) {
                $order[] = ['group' => $group, 'rank' => $rank];
            }
        }

        return $order;
    }

    private function roundsFor(int $size): int
    {
        $rounds = 1;
        $value = 2;

        while ($value < $size) {
            $value *= 2;
            $rounds++;
        }

        return $rounds;
    }

    /**
     * @return array{round: int, number: int, slot: string}|null
     */
    private function next(int $round, int $position, int $rounds): ?array
    {
        if ($round >= $rounds) {
            return null;
        }

        return [
            'round' => $round + 1,
            'number' => intdiv($position, 2) + 1,
            'slot' => $position % 2 === 0 ? 'a' : 'b',
        ];
    }

    /**
     * @param  array<int, array{a: int, b: int, number: int|null}>  $first
     * @return array<string, mixed>
     */
    private function feeder(int $round, int $position, array $first): array
    {
        if ($round === 1) {
            $slot = $first[$position];

            return $slot['number'] === null
                ? ['seed' => $slot['a']]
                : ['winner_of' => ['round' => 1, 'number' => $slot['number']]];
        }

        return ['winner_of' => ['round' => $round, 'number' => $position + 1]];
    }
}
