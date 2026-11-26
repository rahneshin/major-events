<?php

declare(strict_types=1);

namespace App\Enums;

/** وضعیت نتیجه‌ی یک شرکت‌کننده در سری: ok | DNS (نیامد) | DNF (تمام نکرد) | DQ (رد صلاحیت). */
enum HeatResultStatus: string
{
    case Ok = 'ok';
    case Dns = 'DNS';
    case Dnf = 'DNF';
    case Dq = 'DQ';

    public function label(): string
    {
        return __('ranked.status.'.$this->value);
    }

    /** فقط «ok» رتبه می‌گیرد. */
    public function isRanked(): bool
    {
        return $this === self::Ok;
    }
}
