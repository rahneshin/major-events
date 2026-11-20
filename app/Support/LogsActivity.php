<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

trait LogsActivity
{
    public static function logActivity(
        string $action,
        ?Model $subject = null,
        ?int $eventId = null,
        array $properties = [],
    ): void {
        ActivityLog::query()->create([
            'user_id' => Auth::id(),
            'event_id' => $eventId,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'action' => $action,
            'properties' => $properties ?: null,
            'ip' => Request::ip(),
        ]);
    }
}
