<?php

declare(strict_types=1);

namespace App\Enums;

enum MilestoneStatus: string
{
    case Pending = 'pending';
    case Reached = 'reached';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('task.milestone_status.'.$this->value);
    }
}
