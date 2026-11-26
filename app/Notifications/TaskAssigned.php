<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\TaskPriority;
use App\Models\Task;

/**
 * «به شما وظیفه سپرده شد». پیامک فقط برای وظیفه‌ی فوری.
 */
class TaskAssigned extends AppNotification
{
    public function __construct(public Task $task) {}

    public function category(): string
    {
        return 'task_assigned';
    }

    public function smsEligible(): bool
    {
        return $this->task->priority === TaskPriority::Urgent;
    }

    public function toSms(object $notifiable): ?string
    {
        return $this->message();
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $event = $this->task->event;

        return [
            'type' => 'task.assigned',
            'task_id' => $this->task->id,
            'task_uuid' => $this->task->uuid,
            'task_title' => $this->task->title,
            'event_uuid' => $event->uuid,
            'event_title' => $event->title,
            'urgent' => $this->smsEligible(),
            'message' => $this->message(),
            'url' => route('events.show', ['event' => $event->uuid, 'tab' => 'tasks', 'task' => $this->task->uuid]),
        ];
    }

    private function message(): string
    {
        return __($this->smsEligible() ? 'notification.task_assigned_urgent' : 'notification.task_assigned', [
            'task' => $this->task->title,
            'event' => $this->task->event->title,
        ]);
    }
}
