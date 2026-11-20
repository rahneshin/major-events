<?php

declare(strict_types=1);

namespace App\Enums;

enum MainGoalType: string
{
    case Social = 'social';
    case Commercial = 'commercial';
    case Participation = 'participation';
    case Tourism = 'tourism';
    case Performance = 'performance';
    case Mixed = 'mixed';

    public function label(): string
    {
        return match ($this) {
            self::Social => 'اجتماعی / CSR',
            self::Commercial => 'تجاری',
            self::Participation => 'مشارکت همگانی',
            self::Tourism => 'گردشگری ورزشی',
            self::Performance => 'قهرمانی / عملکرد',
            self::Mixed => 'ترکیبی',
        };
    }
}
