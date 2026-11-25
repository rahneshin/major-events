<?php

declare(strict_types=1);

namespace App\Enums;

enum MatchStatus: string
{
    case Scheduled = 'scheduled';
    case Live = 'live';
    case Finished = 'finished';
    case Walkover = 'walkover';
    case Postponed = 'postponed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('competition.match_status.'.$this->value);
    }

    /** بازی‌ای که نتیجه‌ی قطعی دارد و می‌تواند در رده‌بندی بیاید. */
    public function isPlayed(): bool
    {
        return in_array($this, [self::Finished, self::Walkover], true);
    }
}
