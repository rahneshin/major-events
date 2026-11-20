<?php

declare(strict_types=1);

namespace App\Enums;

enum EventScale: string
{
    case Small = 'small';
    case Medium = 'medium';
    case Large = 'large';

    public function label(): string
    {
        return match ($this) {
            self::Small => 'کوچک',
            self::Medium => 'متوسط',
            self::Large => 'بزرگ',
        };
    }
}
