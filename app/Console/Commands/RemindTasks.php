<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Task;
use App\Notifications\TaskDueReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Step 08c — daily reminders for assignees.
 *
 *  - due_soon: open task due within `--days` (default config tasks.reminder_days), once per due date
 *  - overdue : open task past its due date, repeated every 3 days
 *
 * No migration: de-duplication uses the cache (an atomic Cache::add per task/kind).
 * Only todo/doing tasks are reminded (blocked ones cannot be started yet),
 * only for active events and active assignees.
 */
class RemindTasks extends Command
{
    protected $signature = 'tasks:remind {--days= : reminder window in days (default: config tasks.reminder_days)}';

    protected $description = 'Send in-app reminders for tasks that are due soon or overdue';

    public function handle(): int
    {
        $days = max(0, (int) ($this->option('days') !== null && $this->option('days') !== ''
            ? $this->option('days')
            : config('tasks.reminder_days', 1)));

        $limit = now()->addDays($days)->endOfDay();
        $sent = 0;

        Task::query()
            ->whereIn('status', ['todo', 'doing'])
            ->whereNotNull('assignee_id')
            ->whereNotNull('due_at')
            ->where('due_at', '<=', $limit)
            ->with(['assignee', 'event'])
            ->chunkById(200, function ($tasks) use (&$sent) {
                foreach ($tasks as $task) {
                    $assignee = $task->assignee;

                    if ($assignee === null || ! $assignee->isActive() || ! ($task->event?->isActive() ?? false)) {
                        continue;
                    }

                    $overdue = $task->due_at->isPast();
                    $kind = $overdue ? TaskDueReminder::OVERDUE : TaskDueReminder::DUE_SOON;

                    $key = $overdue
                        ? "tasks:remind:{$task->id}:overdue"
                        : "tasks:remind:{$task->id}:due:{$task->due_at->toDateString()}";

                    // atomic: false when the key already exists
                    if (! Cache::add($key, true, $overdue ? now()->addDays(3) : now()->addDays(10))) {
                        continue;
                    }

                    $assignee->notify(new TaskDueReminder($task, $kind));
                    $sent++;
                }
            });

        $this->info("Reminders sent: {$sent}");

        return self::SUCCESS;
    }
}
