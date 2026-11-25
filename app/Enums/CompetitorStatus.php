<?php

declare(strict_types=1);

namespace App\Enums;

enum CompetitorStatus: string
{
    case Registered = 'registered';
    case Confirmed = 'confirmed';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return __('competition.competitor_status.'.$this->value);
    }
}
