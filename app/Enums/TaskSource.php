<?php

declare(strict_types=1);

namespace App\Enums;

enum TaskSource: string
{
    case Manual = 'manual';
    case Ai = 'ai';

    public function label(): string
    {
        return __('task.source.'.$this->value);
    }
}
