<?php

namespace App\Support;

use Hekmatinasser\Verta\Verta;
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
}
