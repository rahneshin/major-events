<?php

declare(strict_types=1);

namespace App\Livewire\Tasks;

use App\Enums\EventStatus;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Services\TaskService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * «وظایف من» (Step 08c): وظایفی که در همه‌ی رویدادها به کاربر جاری سپرده شده.
 *
 * فقط وظایف خود کاربر در رویدادهایی که هنوز می‌تواند ببیند؛ تغییر وضعیت با
 * TaskPolicy@transition authorize می‌شود (viewer یا عضو حذف‌شده نمی‌تواند).
 */
#[Layout('layouts.app')]
class MyTasks extends Component
{
    public const FILTERS = ['open', 'overdue', 'done', 'all'];

    public string $filter = 'open';

    public ?string $error = null;

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, self::FILTERS, true) ? $filter : 'open';
        $this->error = null;
    }

    public function changeStatus(int $id, string $status, TaskService $service): void
    {
        $this->error = null;

        $task = $this->baseQuery()->with('event')->whereKey($id)->firstOrFail();
        $this->authorize('transition', $task);

        if (in_array($task->event->status, [EventStatus::Archived, EventStatus::Cancelled], true)) {
            $this->error = __('task.ui.event_closed');

            return;
        }

        $target = TaskStatus::tryFrom($status);

        if ($target === null) {
            $this->error = __('task.invalid_value');

            return;
        }

        try {
            $service->transition($task, $target);
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render()
    {
        $user = Auth::user();

        $query = $this->baseQuery();

        match ($this->filter) {
            'overdue' => $query->overdue(),
            'done' => $query->where('status', TaskStatus::Done->value),
            'all' => null,
            default => $query->open(),
        };

        $tasks = $query
            ->with(['event', 'committee'])
            ->orderByRaw('due_at is null')
            ->orderBy('due_at')
            ->orderBy('id')
            ->limit(200)
            ->get();

        $counts = [
            'open' => $this->baseQuery()->open()->count(),
            'overdue' => $this->baseQuery()->overdue()->count(),
            'done' => $this->baseQuery()->where('status', TaskStatus::Done->value)->count(),
        ];

        return view('livewire.tasks.my-tasks', [
            'groups' => $tasks->groupBy('event_id'),
            'counts' => $counts,
            'canMove' => fn (Task $task): bool => $user->can('transition', $task),
            'statuses' => array_filter(TaskStatus::cases(), fn ($s) => $s !== TaskStatus::Blocked),
        ])->title(__('task.ui.mine_title'));
    }

    /**
     * @return Builder<Task>
     */
    private function baseQuery(): Builder
    {
        $user = Auth::user();

        return Task::query()
            ->where('assignee_id', $user->id)
            ->whereHas('event', fn (Builder $q) => $q->visibleTo($user));
    }
}
