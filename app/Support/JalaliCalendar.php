<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Month grid of the Jalali (Persian) calendar for the tasks calendar (Step 08c).
 *
 * Uses only Jalali::parse()/format() (the project's Verta wrapper), so no extra
 * dependency or Verta API is assumed. Week starts on Saturday.
 */
final class JalaliCalendar
{
    /** @var list<string> */
    public const MONTHS = [
        1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور',
        7 => 'مهر', 8 => 'آبان', 9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند',
    ];

    /** @var list<string> Saturday … Friday */
    public const WEEKDAYS = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];

    /**
     * @return array{year: int, month: int, name: string, days: int, offset: int, prev: array{0: int, 1: int}, next: array{0: int, 1: int}}
     */
    public static function month(int $year, int $month): array
    {
        [$year, $month] = self::normalize($year, $month);

        $first = Jalali::parse(sprintf('%04d/%02d/01', $year, $month));

        return [
            'year' => $year,
            'month' => $month,
            'name' => self::MONTHS[$month],
            'days' => self::daysInMonth($year, $month),
            // Saturday = 0 … Friday = 6 (Carbon: Sunday = 0 … Saturday = 6)
            'offset' => $first ? (($first->dayOfWeek + 1) % 7) : 0,
            'prev' => $month === 1 ? [$year - 1, 12] : [$year, $month - 1],
            'next' => $month === 12 ? [$year + 1, 1] : [$year, $month + 1],
        ];
    }

    public static function daysInMonth(int $year, int $month): int
    {
        if ($month <= 6) {
            return 31;
        }

        if ($month <= 11) {
            return 30;
        }

        // Esfand: 30 days in a leap year. Verify by a parse/format round trip (no leap algorithm of our own).
        $probe = sprintf('%04d/12/30', $year);

        return Jalali::format(Jalali::parse($probe), 'Y/m/d') === $probe ? 30 : 29;
    }

    /**
     * Key used to match tasks to calendar cells: "1405/09/01" (Latin digits).
     */
    public static function key(int $year, int $month, int $day): string
    {
        return sprintf('%04d/%02d/%02d', $year, $month, $day);
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    public static function today(): array
    {
        [$y, $m, $d] = array_map('intval', explode('/', Jalali::today('Y/m/d')));

        return [$y, $m, $d];
    }

    /**
     * @return array{0: int, 1: int}
     */
    private static function normalize(int $year, int $month): array
    {
        if ($month < 1) {
            return [$year - 1, 12];
        }

        if ($month > 12) {
            return [$year + 1, 1];
        }

        return [$year, $month];
    }
}
