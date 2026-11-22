<?php

declare(strict_types=1);

namespace App\Livewire\App;

use App\Models\Event;
use App\Models\Task;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * پنل کاربر: «پست‌ها و وظایف من» — فقط خواندنی.
 * پست‌ها از committee_user.post و وظایف باز از tasks.assignee_id می‌آیند.
 */
class MyAssignments extends Component
{
    private const TASK_LIMIT = 6;

    public function render()
    {
        $user = Auth::user();

        $events = Event::query()
            ->active()
            ->whereHas('members', fn ($q) => $q->where('users.id', $user->id))
            ->with(['committees' => fn ($q) => $q
                ->whereHas('members', fn ($m) => $m->where('users.id', $user->id))
                ->with(['members' => fn ($m) => $m->where('users.id', $user->id)]),
            ])
            ->orderByRaw('start_date IS NULL')
            ->orderBy('start_date')
            ->limit(6)
            ->get();

        $openTasks = Task::query()
            ->where('assignee_id', $user->id)
            ->whereIn('status', ['todo', 'doing', 'blocked'])
            ->whereHas('event', fn ($q) => $q->active());

        $totalOpen = (clone $openTasks)->count();

        $tasks = $openTasks
            ->with(['event', 'committee'])
            ->orderByRaw('due_at IS NULL')
            ->orderBy('due_at')
            ->limit(self::TASK_LIMIT)
            ->get();

        return view('livewire.app.my-assignments', [
            'events' => $events,
            'tasks' => $tasks,
            'moreTasks' => max(0, $totalOpen - $tasks->count()),
        ]);
    }
}
