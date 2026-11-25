<?php

declare(strict_types=1);

namespace App\Enums;

enum CompetitionStatus: string
{
    case Registration = 'registration';
    case Drawn = 'drawn';
    case Ready = 'ready';
    case Running = 'running';
    case Finished = 'finished';

    public function label(): string
    {
        return __('competition.status.'.$this->value);
    }

    /** نتیجه فقط در این وضعیت‌ها ثبت می‌شود (Step 12C). */
    public function acceptsResults(): bool
    {
        return in_array($this, [self::Ready, self::Running], true);
    }
}
