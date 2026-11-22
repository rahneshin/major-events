<?php

declare(strict_types=1);

namespace App\Support;

/**
 * پول = عدد صحیح «ریال» (قانون ۷ MASTER). این کلاس فقط نمایش و ورودی را مدیریت می‌کند؛
 * هیچ محاسبه‌ای با float روی مبلغ انجام نمی‌شود.
 */
final class Money
{
    public const RIAL = 'rial';

    public const TOMAN = 'toman';

    public static function normalizeUnit(?string $unit): string
    {
        return $unit === self::TOMAN ? self::TOMAN : self::RIAL;
    }

    public static function unitLabel(?string $unit): string
    {
        return self::normalizeUnit($unit) === self::TOMAN ? 'تومان' : 'ریال';
    }

    /**
     * نمایش با جداکننده‌ی هزار و ارقام فارسی؛ در حالت تومان ریال÷۱۰ (با یک رقم اعشار اگر لازم باشد).
     */
    public static function format(int $rial, ?string $unit = self::RIAL, bool $withUnit = true): string
    {
        $unit = self::normalizeUnit($unit);
        $sign = $rial < 0 ? '-' : '';
        $abs = abs($rial);

        if ($unit === self::TOMAN) {
            $whole = intdiv($abs, 10);
            $rest = $abs % 10;
            $text = number_format($whole, 0, '', '٬');
            if ($rest !== 0) {
                $text .= '٫'.$rest;
            }
        } else {
            $text = number_format($abs, 0, '', '٬');
        }

        $text = $sign.PhoneNumber::toPersianDigits($text);

        return $withUnit ? $text.' '.self::unitLabel($unit) : $text;
    }

    /**
     * ورودی کاربر (ارقام فارسی/عربی/لاتین، جداکننده‌ی هزار) → ریال صحیح.
     * اعشار، منفی و متن نامعتبر ← null. تومان × ۱۰.
     */
    public static function parse(?string $input, ?string $unit = self::RIAL): ?int
    {
        if ($input === null) {
            return null;
        }

        $clean = PhoneNumber::toLatinDigits(trim($input));
        $clean = str_replace([',', '٬', '،', ' ', "\u{200c}", "\u{200f}", "\u{200e}"], '', $clean);

        if ($clean === '' || ! preg_match('/^\d{1,16}$/', $clean)) {
            return null;
        }

        $value = (int) $clean;

        if (self::normalizeUnit($unit) === self::TOMAN) {
            if ($value > intdiv(PHP_INT_MAX, 10)) {
                return null;
            }
            $value *= 10;
        }

        return $value;
    }

    public static function maxAmount(): int
    {
        return (int) config('budget.max_amount', 10_000_000_000_000);
    }
}
