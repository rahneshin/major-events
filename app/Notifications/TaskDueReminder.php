<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Task;

/**
 * Reminder for the assignee: in-app always (per preference), SMS only for «due soon».
 * kind: due_soon | overdue
 */
class TaskDueReminder extends AppNotification
{
    public const DUE_SOON = 'due_soon';

    public const OVERDUE = 'overdue';

    public function __construct(
        public Task $task,
        public string $kind,
    ) {}

    public function category(): string
    {
        return 'task_due';
    }

    public function smsEligible(): bool
    {
        return $this->kind === self::DUE_SOON;
    }

    public function toSms(object $notifiable): ?string
    {
        return $this->toArray($notifiable)['message'];
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
            'url' => route('events.show', ['event' => $event->uuid, 'tab' => 'tasks', 'task' => $this->task->uuid]),
            'message' => __($this->kind === self::OVERDUE ? 'task.notify_overdue' : 'task.notify_due_soon', [
                'task' => $this->task->title,
                'event' => $event->title,
                'date' => $date,
            ]),
        ];
    }
}
