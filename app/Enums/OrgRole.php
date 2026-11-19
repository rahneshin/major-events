<?php

namespace App\Enums;

enum OrgRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Manager = 'manager';
    case Member = 'member';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Owner => __('org.role_owner'),
            self::Admin => __('org.role_admin'),
            self::Manager => __('org.role_manager'),
            self::Member => __('org.role_member'),
            self::Viewer => __('org.role_viewer'),
        };
    }

    public function canManageMembers(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }

    public function canUpdateOrganization(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }

    public function rank(): int
    {
        return match ($this) {
            self::Owner => 100,
            self::Admin => 80,
            self::Manager => 60,
            self::Member => 40,
            self::Viewer => 20,
        };
    }
}
