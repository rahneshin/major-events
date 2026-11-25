<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

/**
 * دسترسی بازاریابی (Step 11)؛ همه‌ی متدها با آرگومان رویداد:
 *   $user->can('manage', [CampaignItem::class, $event])
 *
 *  - view: هر کسی که رویداد را می‌بیند (برنامه‌ی بازاریابی و تقویم محتوا برای کل تیم شفاف است).
 *  - manage: مدیر رویداد/سازمان (= EventPolicy@update): برنامه، آیتم‌های تقویم، KPI.
 *
 * این Policy با قرارداد نام (CampaignItem ← CampaignItemPolicy) خودکار کشف می‌شود.
 */
class CampaignItemPolicy
{
    public function view(User $user, Event $event): bool
    {
        return $user->can('view', $event);
    }

    public function manage(User $user, Event $event): bool
    {
        return $user->can('update', $event);
    }
}
