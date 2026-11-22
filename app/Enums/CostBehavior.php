<?php

declare(strict_types=1);

namespace App\Enums;

enum CostBehavior: string
{
    case Fixed = 'fixed';
    case Variable = 'variable';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'ثابت',
            self::Variable => 'متغیر (وابسته به تعداد شرکت‌کننده)',
        };
    }
}
