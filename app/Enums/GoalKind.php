<?php

declare(strict_types=1);

namespace App\Enums;

enum GoalKind: string
{
    case Main = 'main';
    case Secondary = 'secondary';

    public function label(): string
    {
        return match ($this) {
            self::Main => 'هدف اصلی',
            self::Secondary => 'هدف فرعی',
        };
    }
}
