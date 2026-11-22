<?php

declare(strict_types=1);

namespace App\Enums;

enum BudgetVersionStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Superseded = 'superseded';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'مقدماتی (در حال بازنگری)',
            self::Approved => 'مصوب',
            self::Superseded => 'جایگزین‌شده',
        };
    }
}
