<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\MilestoneStatus;
use App\Enums\RiskStatus;
use App\Enums\TaskStatus;
use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * متن فارسی هر ردیف ActivityLog: «وضعیت وظیفه تغییر کرد ← انجام‌شده».
 * تاریخ و کاربر جدا در کامپوننت نمایش داده می‌شوند.
 */
class ActivityDescriber
{
    public static function describe(ActivityLog $log): string
    {
        $key = 'activity.actions.'.$log->action;
        $text = __($key);

        if ($text === $key) {
            $text = $log->action;
        }

        $to = $log->properties['to'] ?? null;

        if (is_string($to) && str_ends_with($log->action, '.status_changed')) {
            $label = match (true) {
                str_starts_with($log->action, 'task.') => TaskStatus::tryFrom($to)?->label(),
                str_starts_with($log->action, 'risk.') => RiskStatus::tryFrom($to)?->label(),
                str_starts_with($log->action, 'milestone.') => MilestoneStatus::tryFrom($to)?->label(),
                default => null,
            };

            if ($label !== null) {
                $text .= ' ← «'.$label.'»';
            }
        }

        return $text;
    }

    /**
     * آخرین اقدامات یک رکورد (جدیدترین اول).
     *
     * @return Collection<int, ActivityLog>
     */
    public static function for(Model $subject, int $limit = 8): Collection
    {
        return ActivityLog::query()
            ->with('user')
            ->where('subject_type', $subject::class)
            ->where('subject_id', $subject->getKey())
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}
