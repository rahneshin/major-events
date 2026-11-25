<?php

declare(strict_types=1);

namespace App\Enums;

enum CampaignStatus: string
{
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Published = 'published';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('marketing.status.'.$this->value);
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Planned, self::InProgress], true);
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Planned => 'bg-ink-100 text-ink-700',
            self::InProgress => 'bg-flag-300/40 text-ink-800',
            self::Published => 'bg-success-50 text-success-700',
            self::Cancelled => 'bg-ink-50 text-ink-400',
        };
    }
}
