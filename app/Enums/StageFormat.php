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

    /** فرمت‌هایی که در این مرحله‌ی توسعه (تا 12D) جدولشان ساخته می‌شود؛ دوحذفی در 12F، سری‌ها در 12E. */
    public function isSupported(): bool
    {
        return in_array($this, [self::RoundRobin, self::SingleElim, self::Groups], true);
    }
}
