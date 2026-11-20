<?php

declare(strict_types=1);

namespace App\Enums;

enum EventType: string
{
    case Mega = 'mega';
    case MultiSport = 'multi_sport';
    case Regional = 'regional';
    case Local = 'local';
    case Youth = 'youth';
    case Para = 'para';
    case Community = 'community';
    case Tournament = 'tournament';
    case League = 'league';
    case Festival = 'festival';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Mega => 'مگا / بین‌المللی',
            self::MultiSport => 'چندرشته‌ای',
            self::Regional => 'منطقه‌ای',
            self::Local => 'محلی',
            self::Youth => 'جوانان',
            self::Para => 'پارالمپیک / معلولین',
            self::Community => 'همگانی / اجتماعی',
            self::Tournament => 'تورنمنت',
            self::League => 'لیگ',
            self::Festival => 'جشنواره ورزشی',
            self::Other => 'سایر',
        };
    }
}
