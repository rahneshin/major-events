<?php

declare(strict_types=1);

namespace App\Services\Competitions\Engine;

use InvalidArgumentException;

/**
 * رتبه‌بندی یک سری (PHP خالص، بدون DB).
 *
 *  - فقط ورودی با وضعیت `ok` و مقدار غیرخالی رتبه می‌گیرد؛ DNS/DNF/DQ و «بدون مقدار» رتبه‌ی `null` دارند.
 *  - `better`: `lower` (زمان) یا `higher` (مسافت/وزن).
 *  - تساوی = رتبه‌ی مشترک و رتبه‌ی بعدی پرش دارد (۱، ۲، ۲، ۴).
 *  - مقدارها در واحد هزارم مقایسه می‌شوند (بدون خطای ممیز شناور).
 */
final class HeatRanker
{
    public const BETTER = ['lower', 'higher'];

    /**
     * @param  list<array{id: int, value?: int|float|string|null, status?: string}>  $entries
     * @return array<int, array{rank: int|null, tied: bool}> کلید = id ورودی
     */
    public function rank(array $entries, string $better = 'lower'): array
    {
        self::assertBetter($better);

        $out = [];
        $ranked = [];

        foreach ($entries as $entry) {
            $id = (int) $entry['id'];
            $milli = self::milli($entry['value'] ?? null);

            if (($entry['status'] ?? 'ok') !== 'ok' || $milli === null) {
                $out[$id] = ['rank' => null, 'tied' => false];

                continue;
            }

            $ranked[] = ['id' => $id, 'm' => $milli];
        }

        usort($ranked, function (array $x, array $y) use ($better): int {
            $cmp = $better === 'lower' ? $x['m'] <=> $y['m'] : $y['m'] <=> $x['m'];

            return $cmp !== 0 ? $cmp : $x['id'] <=> $y['id'];
        });

        $rank = 0;
        $previous = null;
        $counts = [];

        foreach ($ranked as $i => $row) {
            if ($previous === null || $row['m'] !== $previous) {
                $rank = $i + 1;
                $previous = $row['m'];
            }

            $out[$row['id']] = ['rank' => $rank, 'tied' => false];
            $counts[$rank] = ($counts[$rank] ?? 0) + 1;
        }

        foreach ($ranked as $row) {
            $rank = $out[$row['id']]['rank'];
            $out[$row['id']]['tied'] = ($counts[$rank] ?? 0) > 1;
        }

        return $out;
    }

    /** مقدار به هزارم (عدد صحیح)؛ خالی/نامعتبر = null. */
    public static function milli(mixed $value): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return (int) round(((float) $value) * 1000);
    }

    public static function assertBetter(string $better): void
    {
        if (! in_array($better, self::BETTER, true)) {
            throw new InvalidArgumentException("Unsupported ranking direction [{$better}].");
        }
    }
}
