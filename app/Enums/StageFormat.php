<?php

declare(strict_types=1);

namespace App\Enums;

enum StageFormat: string
{
    case RoundRobin = 'round_robin';
    case SingleElim = 'single_elim';
    case DoubleElim = 'double_elim';
    case Groups = 'groups';
    case Heats = 'heats';

    public function label(): string
    {
        return __('competition.format.'.$this->value);
    }

    /** فرمت‌هایی که در این مرحله‌ی توسعه (12A/12B) جدولشان ساخته می‌شود. */
    public function isSupported(): bool
    {
        return $this === self::RoundRobin;
    }
}
