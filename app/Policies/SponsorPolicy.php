<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\EventRole;
use App\Models\Event;
use App\Models\User;

/**
 * دسترسی اسپانسرینگ (Step 11)؛ همه‌ی متدها با آرگومان رویداد:
 *   $user->can('manage', [Sponsor::class, $event])
 *
 *  - manage: مالک/مدیر رویداد، سازنده، مالک/ادمین/مدیر سازمان (= EventPolicy@update).
 *    شامل اسپانسر، دارایی، بسته، تعهد، مدرک و پیوند وظیفه.
 *  - viewModule: مدیریت + رئیس کمیته + عضو؛ بیننده و غیرعضو هیچ. (فرض ⚠️: قراردادها و ارقام
 *    مانند بودجه حساس‌اند؛ اطلاعات تماس و ارقام فقط برای سطح مدیریت در UI نشان داده می‌شود.)
 *
 * این Policy با قرارداد نام (Sponsor ← SponsorPolicy) خودکار کشف می‌شود.
 * محدودیت طرح حساب (`sponsorship`، فقط سازمانی) در SponsorshipService اعمال می‌شود، نه اینجا.
 */
class SponsorPolicy
{
    public function viewModule(User $user, Event $event): bool
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
}
