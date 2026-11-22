<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Notifications\Notification;

/**
 * In-app reminder for the assignee (SMS arrives with Step 13).
 * kind: due_soon | overdue
 */
class TaskDueReminder extends Notification
{
    public const DUE_SOON = 'due_soon';

    public const OVERDUE = 'overdue';

    public function __construct(
        public Task $task,
        public string $kind,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $event = $this->task->event;
        $date = (string) jalali($this->task->due_at);

        return [
            'type' => 'task.'.$this->kind,
            'task_id' => $this->task->id,
            'task_uuid' => $this->task->uuid,
            'task_title' => $this->task->title,
            'event_uuid' => $event->uuid,
            'event_title' => $event->title,
            'due_at' => $this->task->due_at?->toIso8601String(),
            'message' => __($this->kind === self::OVERDUE ? 'task.notify_overdue' : 'task.notify_due_soon', [
                'task' => $this->task->title,
                'event' => $event->title,
                'date' => $date,
            ]),
        ];
    }
}
