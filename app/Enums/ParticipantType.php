<?php

declare(strict_types=1);

namespace App\Enums;

enum ParticipantType: string
{
    case Team = 'team';
    case Individual = 'individual';

    public function label(): string
    {
        return __('competition.participant.'.$this->value);
    }
}
