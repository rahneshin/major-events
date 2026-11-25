<?php

declare(strict_types=1);

namespace App\Enums;

enum ResultStatus: string
{
    case Entered = 'entered';
    case Confirmed = 'confirmed';

    public function label(): string
    {
        return __('competition.result_status.'.$this->value);
    }
}
