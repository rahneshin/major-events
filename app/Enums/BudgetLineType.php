<?php

declare(strict_types=1);

namespace App\Enums;

enum BudgetLineType: string
{
    case Expense = 'expense';
    case Revenue = 'revenue';

    public function label(): string
    {
        return match ($this) {
            self::Expense => 'هزینه',
            self::Revenue => 'درآمد',
        };
    }
}
