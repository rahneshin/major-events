<?php

declare(strict_types=1);

namespace App\Enums;

enum TaskModule: string
{
    case Strategy = 'strategy';
    case Operations = 'operations';
    case Budget = 'budget';
    case Marketing = 'marketing';
    case Sponsorship = 'sponsorship';
    case Risk = 'risk';
    case Logistics = 'logistics';
    case Competitions = 'competitions';
    case Evaluation = 'evaluation';

    public function label(): string
    {
        return __('task.module.'.$this->value);
    }
}
