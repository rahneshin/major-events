<?php

declare(strict_types=1);

namespace App\Enums;

enum TaskPhase: string
{
    case Pre = 'pre';
    case During = 'during';
    case Post = 'post';

    public function label(): string
    {
        return __('task.phase.'.$this->value);
    }
}
