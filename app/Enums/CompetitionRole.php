<?php

declare(strict_types=1);

namespace App\Enums;

enum CompetitionRole: string
{
    case Manager = 'manager';
    case Scorer = 'scorer';

    public function label(): string
    {
        return __('competition.role.'.$this->value);
    }
}
