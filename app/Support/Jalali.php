<?php

namespace App\Support;

use Hekmatinasser\Verta\Verta;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class Jalali
{
    /**
     * تبدیل تاریخ میلادی به شمسی (نمایش)
     */
    public static function format(Carbon|string|null $date, string $format = 'Y/m/d'): ?string
    {
        if ($date === null) {
            return null;
        }

        $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);

        return Verta::instance($carbon)->format($format);
    }

    /**
     * تبدیل رشته شمسی (مثلاً ۱۴۰۵/۰۸/۲۹) به Carbon میلادی
     */
    public static function parse(?string $jalaliDate, string $format = 'Y/m/d'): ?Carbon
    {
        if ($jalaliDate === null || trim($jalaliDate) === '') {
            return null;
        }

        $jalaliDate = PhoneNumber::toLatinDigits(trim($jalaliDate));

        // خروجی انتخاب‌گر تاریخ: میلادی ISO (۲۰۲۶-۱۱-۲۳)
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $jalaliDate, $m) === 1 && (int) $m[1] >= 1700) {
            return checkdate((int) $m[2], (int) $m[3], (int) $m[1])
                ? Carbon::create((int) $m[1], (int) $m[2], (int) $m[3], 0, 0, 0)
                : null;
        }

        try {
            $verta = Verta::parseFormat($format, $jalaliDate);

            return Carbon::instance($verta->datetime());
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * تاریخ امروز شمسی
     */
    public static function today(string $format = 'Y/m/d'): string
    {
        return Verta::now()->format($format);
    }

    /**
     * نمایش نسبی (مثلاً «۲ روز پیش»)
     */
    public static function diffForHumans(Carbon|string|null $date): ?string
    {
        if ($date === null) {
            return null;
        }

        $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);

        return Verta::instance($carbon)->formatDifference();
    }

    private const ORDINALS = [
        1 => 'یکم', 2 => 'دوم', 3 => 'سوم', 4 => 'چهارم', 5 => 'پنجم', 6 => 'ششم', 7 => 'هفتم',
        8 => 'هشتم', 9 => 'نهم', 10 => 'دهم', 11 => 'یازدهم', 12 => 'دوازدهم', 13 => 'سیزدهم',
        14 => 'چهاردهم', 15 => 'پانزدهم', 16 => 'شانزدهم', 17 => 'هفدهم', 18 => 'هجدهم',
        19 => 'نوزدهم', 20 => 'بیستم', 21 => 'بیست و یکم', 22 => 'بیست و دوم', 23 => 'بیست و سوم',
        24 => 'بیست و چهارم', 25 => 'بیست و پنجم', 26 => 'بیست و ششم', 27 => 'بیست و هفتم',
        28 => 'بیست و هشتم', 29 => 'بیست و نهم', 30 => 'سی‌ام', 31 => 'سی و یکم',
    ];

    private const MONTHS = [
        1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور',
        7 => 'مهر', 8 => 'آبان', 9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند',
    ];

    /**
     * @return array{0: int, 1: int, 2: int} [سال، ماه، روز] شمسی
     */
    private static function parts(CarbonInterface|string $date): array
    {
        $carbon = $date instanceof CarbonInterface ? $date : Carbon::parse($date);
        $v = Verta::instance($carbon);

        return [(int) $v->year, (int) $v->month, (int) $v->day];
    }

    /**
     * تاریخ به‌صورت نوشتاری و بدون ساعت: «نهم تیر ۱۴۰۵».
     */
    public static function long(CarbonInterface|string|null $date, bool $withYear = true): ?string
    {
        if ($date === null) {
            return null;
        }

        [$y, $m, $d] = self::parts($date);
        $text = (self::ORDINALS[$d] ?? (string) $d).' '.(self::MONTHS[$m] ?? (string) $m);

        return $withYear ? $text.' '.PhoneNumber::toPersianDigits((string) $y) : $text;
    }

    /**
     * بازه‌ی تاریخ: «نهم تیر تا هفتم آبان ۱۴۰۵»؛ اگر سال‌ها فرق کنند، سال هر دو می‌آید.
     */
    public static function range(CarbonInterface|string|null $from, CarbonInterface|string|null $to): ?string
    {
        if ($from === null) {
            return null;
        }

        if ($to === null) {
            return self::long($from);
        }

        [$y1] = self::parts($from);
        [$y2] = self::parts($to);

        if ($y1 === $y2) {
            return self::long($from, false).' تا '.self::long($to);
        }

        return self::long($from).' تا '.self::long($to);
    }

    /**
     * رشته‌ی «ثبت‌شده در تاریخ … توسط …» بدون ساعت.
     */
    public static function stamp(CarbonInterface|string|null $date, ?string $by = null, string $verb = 'ثبت شده'): string
    {
        $text = $verb.' در تاریخ '.(self::long($date) ?? '—');

        return $by !== null && $by !== '' ? $text.' توسط '.$by : $text;
    }
}
