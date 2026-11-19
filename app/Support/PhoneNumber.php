<?php

namespace App\Support;

class PhoneNumber
{
    /**
     * نرمال‌سازی شماره موبایل ایرانی به فرمت +989xxxxxxxxx
     */
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $phone = self::toLatinDigits(trim($phone));
        $phone = preg_replace('/[\s\-\(\)]+/', '', $phone) ?? '';

        // حذف +98 یا 98 یا 0 ابتدایی
        if (str_starts_with($phone, '+98')) {
            $phone = substr($phone, 3);
        } elseif (str_starts_with($phone, '98') && strlen($phone) >= 12) {
            $phone = substr($phone, 2);
        } elseif (str_starts_with($phone, '0')) {
            $phone = substr($phone, 1);
        }

        // باید ۹ رقم و با ۹ شروع شود
        if (! preg_match('/^9\d{9}$/', $phone)) {
            return null;
        }

        return '+98'.$phone;
    }

    public static function isValid(?string $phone): bool
    {
        return self::normalize($phone) !== null;
    }

    /**
     * نمایش کاربرپسند: 0912 345 6789
     */
    public static function format(?string $phone): ?string
    {
        $normalized = self::normalize($phone);

        if ($normalized === null) {
            return null;
        }

        $local = '0'.substr($normalized, 3); // 09xxxxxxxxx

        return substr($local, 0, 4).' '.substr($local, 4, 3).' '.substr($local, 7);
    }

    public static function toLatinDigits(string $value): string
    {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $latin = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace($arabic, $latin, str_replace($persian, $latin, $value));
    }

    public static function toPersianDigits(string|int|float $value): string
    {
        $latin = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

        return str_replace($latin, $persian, (string) $value);
    }
}
