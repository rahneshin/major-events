<?php

declare(strict_types=1);

namespace App\Enums;

enum EventRole: string
{
    case EventOwner = 'event_owner';
    case EventManager = 'event_manager';
    case CommitteeHead = 'committee_head';
    case Member = 'member';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::EventOwner => 'مالک رویداد',
            self::EventManager => 'مدیر رویداد',
            self::CommitteeHead => 'رئیس کمیته',
            self::Member => 'عضو',
            self::Viewer => 'بیننده',
        };
    }

    public function canManageMembers(): bool
    {
        return in_array($this, [self::EventOwner, self::EventManager], true);
    }

    public function canUpdateEvent(): bool
    {
        return in_array($this, [self::EventOwner, self::EventManager], true);
    }

    public function rank(): int
    {
        return match ($this) {
            self::EventOwner => 100,
            self::EventManager => 80,
            self::CommitteeHead => 60,
            self::Member => 40,
            self::Viewer => 20,
        };
    }
}
