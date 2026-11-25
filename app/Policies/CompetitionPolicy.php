<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CompetitionRole;
use App\Models\Competition;
use App\Models\Event;
use App\Models\User;

/**
 * مجوز ماژول مسابقات (Step 12).
 *
 *  - دیدن: هر کسی که رویداد را می‌بیند.
 *  - ساخت/حذف ورزش و تعیین مسئول/ثبت‌کننده: سطح رویداد (EventPolicy@update).
 *  - مدیریت (ثبت‌نام، قرعه، جدول): سطح رویداد یا «مسئول ورزش» همان ورزش.
 *  - ثبت نتیجه: مدیریت یا «ثبت‌کننده‌ی» همان ورزش.
 *  - تأیید نتیجه: فقط مدیریت.
 * سنجش طرح (feature = competitions) در CompetitionService است، نه اینجا.
 */
class CompetitionPolicy
{
    public function viewAny(User $user, Event $event): bool
    {
        return $user->can('view', $event);
    }

    public function create(User $user, Event $event): bool
    {
        return $user->can('update', $event);
    }

    public function view(User $user, Competition $competition): bool
    {
        return $user->can('view', $competition->event);
    }

    public function manage(User $user, Competition $competition): bool
    {
        return $user->can('update', $competition->event)
            || $this->hasRole($user, $competition, CompetitionRole::Manager);
    }

    public function enterResult(User $user, Competition $competition): bool
    {
        return $this->manage($user, $competition)
            || $this->hasRole($user, $competition, CompetitionRole::Scorer);
    }

    public function confirmResult(User $user, Competition $competition): bool
    {
        return $this->manage($user, $competition);
    }

    public function assignRoles(User $user, Competition $competition): bool
    {
        return $user->can('update', $competition->event);
    }

    public function delete(User $user, Competition $competition): bool
    {
        return $user->can('update', $competition->event);
    }

    private function hasRole(User $user, Competition $competition, CompetitionRole $role): bool
    {
        // نقش ورزش فقط برای کسی که هنوز می‌تواند رویداد را ببیند معتبر است
        if (! $user->can('view', $competition->event)) {
            return false;
        }

        return $competition->members()
            ->where('users.id', $user->id)
            ->wherePivot('role', $role->value)
            ->exists();
    }
}
