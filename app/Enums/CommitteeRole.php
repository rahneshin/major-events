<?php

declare(strict_types=1);

namespace App\Enums;

enum CommitteeRole: string
{
    case Head = 'head';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Head => __('committee.role_head'),
            self::Member => __('committee.role_member'),
        };
    }
}
