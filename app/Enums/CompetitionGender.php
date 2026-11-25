<?php

declare(strict_types=1);

namespace App\Enums;

enum CompetitionGender: string
{
    case Men = 'men';
    case Women = 'women';
    case Mixed = 'mixed';
    case Open = 'open';

    public function label(): string
    {
        return __('competition.gender.'.$this->value);
    }
}
