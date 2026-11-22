<?php

declare(strict_types=1);

namespace App\Enums;

enum RiskCategory: string
{
    case Safety = 'safety';
    case Medical = 'medical';
    case Weather = 'weather';
    case Crowd = 'crowd';
    case Financial = 'financial';
    case Legal = 'legal';
    case Security = 'security';
    case Technical = 'technical';
    case Reputation = 'reputation';

    public function label(): string
    {
        return __('risk.category.'.$this->value);
    }
}
