<?php

declare(strict_types=1);

namespace App\Support;

/** مخفف‌کردن نام برای صفحه‌ی عمومی (حریم خصوصی زیر ۱۸ سال): «علی رضایی» ← «ع. ر.». */
final class NameMasker
{
    public static function abbreviate(string $name): string
    {
        $parts = preg_split('/[\s\x{200C}]+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode(' ', array_map(fn (string $p): string => mb_substr($p, 0, 1).'.', $parts));
    }
}
