<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Task;
use App\Notifications\TaskAssigned;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Step 13 — tells the new assignee about a task. Rules:
 *  - only for actions done by a signed-in user (no actor = seed/console/system, stay quiet);
 *  - never the actor themself; only active users in active events;
 *  - a failing notification must never break saving the task.
 */
class TaskObserver
{
    public function created(Task $task): void
    {
        $this->notifyAssignee($task);
    }

    public function updated(Task $task): void
    {
        if ($task->wasChanged('assignee_id')) {
            $this->notifyAssignee($task);
        }
    }

    private function notifyAssignee(Task $task): void
    {
        if ($task->assignee_id === null || Auth::guest()) {
            return;
        }

        if ((int) Auth::id() === (int) $task->assignee_id) {
            return;
        }

        $assignee = $task->assignee;
        $event = $task->event;

        if ($assignee === null || ! $assignee->isActive() || $event === null || ! $event->isActive()) {
            return;
        }

        try {
            $assignee->notify(new TaskAssigned($task));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
