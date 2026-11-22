<?php

declare(strict_types=1);

namespace App\Enums;

enum IncidentSeverity: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    public function label(): string
    {
        return __('risk.severity.'.$this->value);
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Low => 'bg-pool-50 text-pool-700',
            self::Medium => 'bg-flag-300/40 text-ink-800',
            self::High => 'bg-flag-500 text-ink-950',
            self::Critical => 'bg-signal-500 text-white',
        };
    }
}
