<?php

use App\Services\EntitlementService;
use App\Support\Jalali;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Auth;

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
    function jalali(\Carbon\CarbonInterface|string|null $date, string $format = 'Y/m/d'): ?string
    {
        return Jalali::format($date, $format);
    }
}

if (! function_exists('jalali_long')) {
    /** «نهم تیر ۱۴۰۵» (ارقام فارسی، بدون ساعت) */
    function jalali_long(\Carbon\CarbonInterface|string|null $date, bool $withYear = true): string
    {
        return fa_num(Jalali::long($date, $withYear) ?? '—');
    }
}

if (! function_exists('jalali_range')) {
    /** «نهم تیر تا هفتم آبان ۱۴۰۵» */
    function jalali_range(\Carbon\CarbonInterface|string|null $from, \Carbon\CarbonInterface|string|null $to = null): string
    {
        return fa_num(Jalali::range($from, $to) ?? '—');
    }
}

if (! function_exists('person_name')) {
    function person_name(?\App\Models\User $user): string
    {
        if ($user === null) {
            return 'کاربر حذف‌شده';
        }

        return $user->name ?: (phone($user->phone) ?? '—');
    }
}

if (! function_exists('phone')) {
    function phone(?string $phone): ?string
    {
        return PhoneNumber::format($phone);
    }
}

if (! function_exists('feature')) {
    /**
     * Check whether the authenticated user (or given user) has a plan feature.
     */
    function feature(string $feature, ?\App\Models\User $user = null): bool
    {
        $user ??= Auth::user();

        if ($user === null) {
            return false;
        }

        return app(EntitlementService::class)->can($user, $feature);
    }
}

if (! function_exists('limit_reached')) {
    /**
     * True when current usage has reached or exceeded the plan limit.
     */
    function limit_reached(string $key, int $currentUsage, ?\App\Models\User $user = null): bool
    {
        $user ??= Auth::user();

        if ($user === null) {
            return true;
        }

        return ! app(EntitlementService::class)->isWithinLimit($user, $key, $currentUsage);
    }
}
