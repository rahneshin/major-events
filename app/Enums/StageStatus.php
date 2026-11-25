<?php

declare(strict_types=1);

namespace App\Enums;

enum StageStatus: string
{
    case Pending = 'pending';
    case Drawn = 'drawn';
    case Running = 'running';
    case Finished = 'finished';

    public function label(): string
    {
        return __('competition.stage_status.'.$this->value);
    }
}
