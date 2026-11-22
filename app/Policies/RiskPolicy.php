<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\EventRole;
use App\Models\Event;
use App\Models\User;

/**
 * دسترسی ریسک و حادثه (Step 10)؛ همه‌ی متدها با آرگومان رویداد:
 *   $user->can('manage', [Risk::class, $event])
 *
 *  - viewRegister: هر کسی که رویداد را می‌بیند (شفافیت ریسک برای کل تیم و ناظر).
 *  - manage: مالک/مدیر رویداد، سازنده، مالک/ادمین/مدیر سازمان (= EventPolicy@update).
 *    شامل ساخت/ویرایش/حذف ریسک، قالب‌ها، وظیفه‌ی کنترلی، و ویرایش/حل/حذف حادثه.
 *  - reportIncident: ثبت حادثه‌ی روز رویداد: سطح مدیریت + رئیس کمیته + عضو (نه بیننده).
 *
 * این Policy با قرارداد نام (Risk ← RiskPolicy) خودکار کشف می‌شود.
 */
class RiskPolicy
{
    public function viewRegister(User $user, Event $event): bool
    {
        return $user->can('view', $event);
    }

    public function manage(User $user, Event $event): bool
    {
        return $user->can('update', $event);
    }

    public function reportIncident(User $user, Event $event): bool
    {
        if ($this->manage($user, $event)) {
            return true;
        }

        if (! $user->can('view', $event)) {
            return false;
        }

        return in_array($event->roleOf($user), [EventRole::CommitteeHead, EventRole::Member], true);
    }
}
