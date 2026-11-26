<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Where a stored notification should lead. Newer notifications carry `url`;
 * older ones (before Step 13) are resolved from their event/task ids.
 */
class NotificationLink
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function for(array $data): ?string
    {
        $url = $data['url'] ?? null;

        if (is_string($url) && str_starts_with($url, url('/'))) {
            return $url;
        }

        $eventUuid = $data['event_uuid'] ?? null;

        if (! is_string($eventUuid) || $eventUuid === '') {
            return null;
        }

        $taskUuid = $data['task_uuid'] ?? null;

        if (is_string($taskUuid) && $taskUuid !== '') {
            return route('events.show', ['event' => $eventUuid, 'tab' => 'tasks', 'task' => $taskUuid]);
        }

        return route('events.show', ['event' => $eventUuid]);
    }
}
