<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * ورودی/نمایش مقدار نتیجه‌ی رتبه‌ای.
 *  - ورودی: رقم فارسی/عربی، جداکننده‌ی اعشار «.», «٫», «،», «/»؛ زمان «م:ث.اعشار» یا «س:م:ث».
 *  - خروجی زمان: زیر ۶۰ ثانیه ساده، بالاتر «م:ث.اعشار».
 */
final class HeatValue
{
    public const MAX = 100000000;

    public static function normalize(string $text): string
    {
        $text = strtr(trim($text), [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '٫' => '.', '،' => '.', ',' => '.', '/' => '.',
        ]);

        return preg_replace('/\s+/u', '', $text) ?? '';
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function parse(mixed $input): ?float
    {
        $text = self::normalize((string) $input);

        if ($text === '') {
            return null;
        }

        if (str_contains($text, ':')) {
            $segments = explode(':', $text);

            if (count($segments) > 3 || ! self::validTime($segments)) {
                throw new InvalidArgumentException(__('ranked.errors.value_invalid'));
            }

            $seconds = 0.0;
            foreach ($segments as $segment) {
                $seconds = $seconds * 60 + (float) $segment;
            }
            $value = $seconds;
        } elseif (preg_match('/^\d+(\.\d+)?$/', $text) === 1) {
            $value = (float) $text;
        } else {
            throw new InvalidArgumentException(__('ranked.errors.value_invalid'));
        }

        if ($value < 0 || $value > self::MAX) {
            throw new InvalidArgumentException(__('ranked.errors.value_invalid'));
        }

        return round($value, 3);
    }

    public static function format(?float $value, string $kind = 'time'): string
    {
        if ($value === null) {
            return '—';
        }

        if ($kind === 'time' && $value >= 60) {
            $whole = (int) floor($value);
            $fraction = round($value - $whole, 3);
            $hours = intdiv($whole, 3600);
            $minutes = intdiv($whole % 3600, 60);
            $seconds = $whole % 60;
            $suffix = $fraction > 0 ? ltrim(rtrim(number_format($fraction, 3, '.', ''), '0'), '0') : '';

            return $hours > 0
                ? sprintf('%d:%02d:%02d%s', $hours, $minutes, $seconds, $suffix)
                : sprintf('%d:%02d%s', $minutes, $seconds, $suffix);
        }

        $text = rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');

        return $text === '' ? '0' : $text;
    }

    /**
     * @param  list<string>  $segments
     */
    private static function validTime(array $segments): bool
    {
        foreach ($segments as $i => $segment) {
            $last = $i === count($segments) - 1;
            $pattern = $last ? '/^\d{1,2}(\.\d+)?$/' : '/^\d{1,3}$/';

            if (preg_match($pattern, $segment) !== 1) {
                return false;
            }

            // دقیقه/ثانیه‌ی میانی باید زیر ۶۰ باشد (ساعت آزاد است)
            if ($i > 0 && (float) $segment >= 60) {
                return false;
            }
        }

        return true;
    }
}
