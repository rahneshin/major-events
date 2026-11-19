<?php

namespace App\Enums;

enum AccountType: string
{
    case Individual = 'individual';
    case Organizational = 'organizational';

    public function label(): string
    {
        return match ($this) {
            self::Individual => __('account.type_individual'),
            self::Organizational => __('account.type_organizational'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Individual => __('account.type_individual_desc'),
            self::Organizational => __('account.type_organizational_desc'),
        };
    }
}
