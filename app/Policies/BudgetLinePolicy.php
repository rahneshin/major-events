<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\EventRole;
use App\Models\Event;
use App\Models\User;

/**
 * دسترسی بودجه (Step 09)؛ همه‌ی متدها با آرگومان رویداد:
 *   $user->can('manage', [BudgetLine::class, $event])
 *
 *  - manage / approve: مالک/مدیر رویداد، سازنده، مالک/ادمین/مدیر سازمان (= EventPolicy@update).
 *    شامل خطوط، تراکنش‌ها، رسید، نسخه‌ها و تنظیم هشدار.
 *  - viewSummary: فقط «مجموع‌ها» برای رئیس کمیته و عضو؛ بیننده‌ی رویداد و غیرعضو هیچ.
 *
 * این Policy با قرارداد نام (BudgetLine ← BudgetLinePolicy) خودکار کشف می‌شود؛
 * نیازی به ثبت در AppServiceProvider نیست.
 */
class BudgetLinePolicy
{
    public function viewSummary(User $user, Event $event): bool
    {
        if ($this->manage($user, $event)) {
            return true;
        }

        if (! $user->can('view', $event)) {
            return false;
        }

        return in_array($event->roleOf($user), [EventRole::CommitteeHead, EventRole::Member], true);
    }

    public function manage(User $user, Event $event): bool
    {
        return $user->can('update', $event);
    }

    public function approve(User $user, Event $event): bool
    {
        return $this->manage($user, $event);
    }
}
