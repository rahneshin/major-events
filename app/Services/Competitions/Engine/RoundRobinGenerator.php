<?php

declare(strict_types=1);

namespace App\Services\Competitions\Engine;

/**
 * لیگ دوره‌ای (روش دایره‌ای).
 *
 *  - تعداد فرد ← یک «استراحت» (bye) اضافه می‌شود؛ هر شرکت‌کننده در هر دور حداکثر یک بازی دارد.
 *  - عنصر اول ثابت است و بقیه هر دور یک خانه می‌چرخند.
 *  - دور برگشت = دور رفت با جای دو طرف عوض‌شده و شماره‌ی دور پیوسته (مثلاً ۱۵ تیم رفت‌وبرگشت = دور ۱ تا ۳۰).
 *
 * خروجی فقط داده است (بدون DB): کنترل کیفیت با تست‌های Unit.
 */
final class RoundRobinGenerator
{
    /**
     * @param  list<int>  $ids  شناسه‌ی شرکت‌کننده‌ها به ترتیب خانه‌ی قرعه
     * @param  int  $legs  1 = تک‌دور، 2 = رفت‌وبرگشت
     * @return array{
     *     fixtures: list<array{round: int, leg: int, number: int, a: int, b: int}>,
     *     byes: array<int, int>,
     *     rounds: int
     * } byes: شماره‌ی دور ← شناسه‌ی شرکت‌کننده‌ای که استراحت دارد
     */
    public function generate(array $ids, int $legs = 1): array
    {
        $ids = array_values($ids);

        if (count($ids) < 2) {
            throw new \InvalidArgumentException('At least two participants are required.');
        }

        if (count(array_unique($ids)) !== count($ids)) {
            throw new \InvalidArgumentException('Participant ids must be unique.');
        }

        if (! in_array($legs, [1, 2], true)) {
            throw new \InvalidArgumentException('legs must be 1 or 2.');
        }

        /** @var list<int|null> $slots */
        $slots = $ids;

        if (count($slots) % 2 === 1) {
            $slots[] = null;
        }

        $size = count($slots);
        $roundsPerLeg = $size - 1;
        $half = intdiv($size, 2);

        $fixtures = [];
        $byes = [];
        $rotation = $slots;

        // دور رفت
        for ($r = 0; $r < $roundsPerLeg; $r++) {
            $number = 0;

            for ($i = 0; $i < $half; $i++) {
                $a = $rotation[$i];
                $b = $rotation[$size - 1 - $i];

                if ($a === null || $b === null) {
                    $byes[$r + 1] = (int) ($a ?? $b);

                    continue;
                }

                // جابه‌جایی نوبتی خانه/مهمان
                if (($r + $i) % 2 === 1) {
                    [$a, $b] = [$b, $a];
                }

                $fixtures[] = [
                    'round' => $r + 1,
                    'leg' => 1,
                    'number' => ++$number,
                    'a' => (int) $a,
                    'b' => (int) $b,
                ];
            }

            // چرخش: آخرین عنصر به خانه‌ی دوم می‌رود، عنصر اول ثابت می‌ماند
            $last = array_pop($rotation);
            array_splice($rotation, 1, 0, [$last]);
        }

        if ($legs === 2) {
            $firstLeg = $fixtures;

            foreach ($firstLeg as $match) {
                $fixtures[] = [
                    'round' => $match['round'] + $roundsPerLeg,
                    'leg' => 2,
                    'number' => $match['number'],
                    'a' => $match['b'],
                    'b' => $match['a'],
                ];
            }

            foreach ($byes as $round => $id) {
                $byes[$round + $roundsPerLeg] = $id;
            }
        }

        ksort($byes);

        return [
            'fixtures' => $fixtures,
            'byes' => $byes,
            'rounds' => $roundsPerLeg * $legs,
        ];
    }
}
