<?php

use App\Support\Jalali;
use App\Support\PhoneNumber;

if (! function_exists('fa_num')) {
    /**
     * تبدیل اعداد لاتین به فارسی برای نمایش
     */
    function fa_num(string|int|float|null $value): string
    {
        if ($value === null) {
            return '';
        }

        return PhoneNumber::toPersianDigits($value);
    }
}

if (! function_exists('jalali')) {
    function jalali(\Carbon\Carbon|string|null $date, string $format = 'Y/m/d'): ?string
    {
        return Jalali::format($date, $format);
    }
}

if (! function_exists('phone')) {
    function phone(?string $phone): ?string
    {
        return PhoneNumber::format($phone);
    }
}
