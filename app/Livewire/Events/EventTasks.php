<?php

declare(strict_types=1);

namespace App\Livewire\Events;

use App\Enums\EventRole;
use App\Enums\EventStatus;
use App\Enums\MilestoneStatus;
use App\Enums\TaskModule;
use App\Enums\TaskPhase;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Exceptions\LimitExceededException;
use App\Models\Committee;
use App\Models\Event;
use App\Models\Milestone;
use App\Models\Task;
use App\Models\User;
use App\Services\MilestoneService;
use App\Services\TaskService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * تب «وظایف» در هاب رویداد (Step 08b: لیست/فرم/جزئیات/نقاط عطف، 08c: کانبان و تقویم شمسی).
 *
 * قواعد:
 *  - منطق فقط در TaskService / MilestoneService؛ این کامپوننت نازک است.
 *  - هر اکشن Livewire جداگانه با TaskPolicy / MilestonePolicy authorize می‌شود
 *    (پرچم‌های `can…` در view فقط برای نمایش دکمه‌ها هستند و Policy را تکرار می‌کنند).
 *  - هر شناسه فقط داخل همین رویداد پیدا می‌شود (firstOrFail روی $this->event).
 */
class EventTasks extends Component
{
    use WithFileUploads;

    public const VIEWS = ['list', 'board', 'calendar'];

    public Event $event;

    public string $mode = 'list';

    // ---- فیلترها (مشترک بین لیست و کانبان)
    public string $fCommittee = '';

    public string $fStatus = '';

    public string $fAssignee = '';

    public string $fPhase = '';

    public string $fDue = '';

    public bool $fMine = false;

    public string $fSearch = '';

    public string $sort = 'position';

    // ---- فرم وظیفه
    public bool $showForm = false;

    public ?int $editingId = null;

    public ?int $formParentId = null;

    public string $title = '';

    public string $description = '';

    public string $committeeId = '';

    public string $assigneeId = '';

    public string $phase = 'pre';

    public string $module = 'operations';

    public string $priority = 'medium';

    public string $startAt = '';

    public string $dueAt = '';

    public string $hours = '';

    // ---- پنل جزئیات
    public ?int $openId = null;

    public string $commentBody = '';

    public string $subtaskTitle = '';

    public string $depPick = '';

    /** @var mixed Livewire TemporaryUploadedFile|null */
    public $attachment = null;

    // ---- نقاط عطف
    public bool $showMilestoneForm = false;

    public ?int $editingMilestoneId = null;

    public string $msTitle = '';

    public string $msDescription = '';

    public string $msDue = '';

    public ?string $error = null;

    public ?string $notice = null;

    public function mount(Event $event): void
    {
        $this->authorize('view', $event);
        $this->event = $event;

        // پیوند مستقیم: ?tab=tasks&task=<uuid> (از «وظایف من» و اعلان‌ها)
        $uuid = request()->query('task');
        if (is_string($uuid) && $uuid !== '') {
            $task = $this->event->tasks()->where('uuid', $uuid)->first();

            if ($task !== null) {
                $this->authorize('view', $task);
                $this->openId = $task->id;
            }
        }
    }

    // ============================================================ navigation

    public function setView(string $view): void
    {
        $this->mode = in_array($view, self::VIEWS, true) ? $view : 'list';
        $this->clearMessages();
    }

    public function resetFilters(): void
    {
        $this->reset(['fCommittee', 'fStatus', 'fAssignee', 'fPhase', 'fDue', 'fMine', 'fSearch']);
        $this->sort = 'position';
    }

    // ============================================================== form (08b)

    public function openCreate(?int $parentId = null): void
    {
        $parent = $parentId !== null ? $this->findTask($parentId) : null;

        if ($parent !== null) {
            $this->authorize('create', [Task::class, $this->event, $parent->committee]);
        } else {
            abort_unless($this->canCreateAny(), 403);
        }

        $this->resetForm();
        $this->formParentId = $parent?->id;
        $this->committeeId = (string) ($parent?->committee_id ?? '');
        $this->showForm = true;
    }

    /**
     * از تقویم: ساخت وظیفه با موعد روزِ انتخاب‌شده ($iso = YYYY-MM-DD میلادی).
     */
    public function openCreateOn(string $iso): void
    {
        $this->openCreate();

        if ($this->parseIso($iso) instanceof Carbon) {
            $this->dueAt = $iso;
        }
    }

    public function openEdit(int $id): void
    {
        $task = $this->findTask($id);
        $this->authorize('update', $task);

        $this->resetForm();
        $this->openId = null; // پنل جزئیات بسته می‌شود تا فرم دیده شود
        $this->editingId = $task->id;
        $this->title = $task->title;
        $this->description = (string) $task->description;
        $this->committeeId = (string) ($task->committee_id ?? '');
        $this->assigneeId = (string) ($task->assignee_id ?? '');
        $this->phase = $task->phase->value;
        $this->module = $task->module->value;
        $this->priority = $task->priority->value;
        $this->startAt = $task->start_at?->toDateString() ?? '';
        $this->dueAt = $task->due_at?->toDateString() ?? '';
        $this->hours = $task->estimated_hours !== null ? rtrim(rtrim((string) $task->estimated_hours, '0'), '.') : '';
        $this->showForm = true;
    }

    public function cancelForm(): void
    {
        $this->resetForm();
    }

    public function save(TaskService $service): void
    {
        $this->clearMessages();

        if ($this->isClosed()) {
            $this->error = __('task.ui.event_closed');

            return;
        }

        $this->validate([
            'title' => ['required', 'string', 'max:'.TaskService::MAX_TITLE_LENGTH],
            'description' => ['nullable', 'string', 'max:5000'],
            'committeeId' => ['nullable', 'integer'],
            'assigneeId' => ['nullable', 'integer'],
            'phase' => ['required', 'in:'.implode(',', array_column(TaskPhase::cases(), 'value'))],
            'module' => ['required', 'in:'.implode(',', array_column(TaskModule::cases(), 'value'))],
            'priority' => ['required', 'in:'.implode(',', array_column(TaskPriority::cases(), 'value'))],
            'startAt' => ['nullable', 'string', 'max:20'],
            'dueAt' => ['nullable', 'string', 'max:20'],
            'hours' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
        ], [], [
            'title' => __('task.ui.f_title'),
            'description' => __('task.ui.f_description'),
            'hours' => __('task.ui.f_hours'),
        ]);

        $start = $this->parseIso($this->startAt);
        $due = $this->parseIso($this->dueAt);

        if ($start === false) {
            $this->addError('startAt', __('task.date_invalid'));

            return;
        }
        if ($due === false) {
            $this->addError('dueAt', __('task.date_invalid'));

            return;
        }

        $data = [
            'title' => $this->title,
            'description' => $this->description,
            'committee_id' => $this->committeeId !== '' ? (int) $this->committeeId : null,
            'assignee_id' => $this->assigneeId !== '' ? (int) $this->assigneeId : null,
            'phase' => $this->phase,
            'module' => $this->module,
            'priority' => $this->priority,
            'start_at' => $start?->startOfDay(),
            // موعد = پایان همان روز تا وظیفه‌ی «امروز» از ساعت ۰۰:۰۱ تأخیردار حساب نشود
            'due_at' => $due?->endOfDay(),
            'estimated_hours' => $this->hours !== '' ? $this->hours : null,
        ];

        try {
            if ($this->editingId !== null) {
                $task = $this->findTask($this->editingId);
                $this->authorize('update', $task);

                // انتقال به کمیته‌ی دیگر = داشتن حق ساخت در آن کمیته
                if ($data['committee_id'] !== (int) ($task->committee_id ?? 0) && $data['committee_id'] !== null) {
                    $this->authorize('create', [Task::class, $this->event, $this->findCommittee($data['committee_id'])]);
                }

                $service->update($task, $data);
                $this->notice = __('task.updated');
            } else {
                $parent = $this->formParentId !== null ? $this->findTask($this->formParentId) : null;
                $committee = $data['committee_id'] !== null
                    ? $this->findCommittee($data['committee_id'])
                    : $parent?->committee;

                $this->authorize('create', [Task::class, $this->event, $committee]);

                $data['parent_id'] = $parent?->id;
                $service->create($this->event, Auth::user(), $data);
                $this->notice = __('task.created');
            }
        } catch (InvalidArgumentException|LimitExceededException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->resetForm();
    }

    public function deleteTask(int $id, TaskService $service): void
    {
        $this->clearMessages();

        $task = $this->findTask($id);
        $this->authorize('delete', $task);

        $service->delete($task);

        if ($this->openId === $id) {
            $this->openId = null;
        }

        $this->notice = __('task.deleted');
    }

    // ========================================================== status / board

    /**
     * تغییر وضعیت (لیست، پنل جزئیات و کشیدن-رها در کانبان).
     * `blocked` فقط خودکار است؛ سرویس آن را رد می‌کند.
     */
    public function changeStatus(int $id, string $status, TaskService $service): void
    {
        $this->clearMessages();

        if ($this->isClosed()) {
            $this->error = __('task.ui.event_closed');

            return;
        }

        $task = $this->findTask($id);
        $this->authorize('transition', $task);

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

    // ============================================================== detail panel

    public function openTask(int $id): void
    {
        $task = $this->findTask($id);
        $this->authorize('view', $task);

        $this->clearMessages();
        $this->openId = $task->id;
        $this->reset(['commentBody', 'subtaskTitle', 'depPick', 'attachment']);
    }

    public function closeTask(): void
    {
        $this->openId = null;
        $this->clearMessages();
    }

    public function addSubtask(TaskService $service): void
    {
        $this->clearMessages();

        $parent = $this->currentTask();
        $this->authorize('create', [Task::class, $this->event, $parent->committee]);

        try {
            $service->create($this->event, Auth::user(), [
                'title' => $this->subtaskTitle,
                'parent_id' => $parent->id,
            ]);
            $this->subtaskTitle = '';
        } catch (InvalidArgumentException|LimitExceededException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function addDependency(TaskService $service): void
    {
        $this->clearMessages();

        $task = $this->currentTask();
        $this->authorize('manageDependencies', $task);

        if ($this->depPick === '') {
            return;
        }

        try {
            $service->addDependency($task, $this->findTask((int) $this->depPick));
            $this->depPick = '';
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function removeDependency(int $dependsOnId, TaskService $service): void
    {
        $this->clearMessages();

        $task = $this->currentTask();
        $this->authorize('manageDependencies', $task);

        $service->removeDependency($task, $this->findTask($dependsOnId));
    }

    public function addComment(TaskService $service): void
    {
        $this->clearMessages();

        $task = $this->currentTask();
        $this->authorize('comment', $task);

        try {
            $service->addComment($task, Auth::user(), $this->commentBody);
            $this->commentBody = '';
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function deleteComment(int $commentId, TaskService $service): void
    {
        $this->clearMessages();

        $task = $this->currentTask();
        $comment = $task->comments()->whereKey($commentId)->firstOrFail();
        $this->authorize('deleteComment', [$task, $comment]);

        $service->deleteComment($comment);
    }

    public function uploadAttachment(TaskService $service): void
    {
        $this->clearMessages();

        $task = $this->currentTask();
        $this->authorize('attach', $task);

        $this->validate([
            'attachment' => ['required', 'file', 'max:'.(int) config('tasks.attachments.max_kb', 5120)],
        ], [], ['attachment' => __('task.ui.attachment')]);

        try {
            $service->addAttachment($task, Auth::user(), $this->attachment);
            $this->reset('attachment');
            $this->notice = __('task.ui.attachment_added');
        } catch (InvalidArgumentException|LimitExceededException $e) {
            $this->reset('attachment');
            $this->error = $e->getMessage();
        }
    }

    public function removeAttachment(int $attachmentId, TaskService $service): void
    {
        $this->clearMessages();

        $task = $this->currentTask();
        $attachment = $task->attachments()->whereKey($attachmentId)->firstOrFail();
        $this->authorize('removeAttachment', [$task, $attachment]);

        $service->removeAttachment($attachment);
    }

    // ================================================================ milestones

    public function openMilestoneForm(?int $id = null): void
    {
        if ($id === null) {
            $this->authorize('create', [Milestone::class, $this->event]);
            $this->resetMilestoneForm();
        } else {
            $milestone = $this->findMilestone($id);
            $this->authorize('update', $milestone);

            $this->resetMilestoneForm();
            $this->editingMilestoneId = $milestone->id;
            $this->msTitle = $milestone->title;
            $this->msDescription = (string) $milestone->description;
            $this->msDue = $milestone->due_at?->toDateString() ?? '';
        }

        $this->showMilestoneForm = true;
    }

    public function cancelMilestoneForm(): void
    {
        $this->resetMilestoneForm();
    }

    public function saveMilestone(MilestoneService $service): void
    {
        $this->clearMessages();

        $this->validate([
            'msTitle' => ['required', 'string', 'max:'.MilestoneService::MAX_TITLE_LENGTH],
            'msDescription' => ['nullable', 'string', 'max:2000'],
            'msDue' => ['nullable', 'string', 'max:20'],
        ], [], ['msTitle' => __('task.ui.f_title')]);

        $due = $this->parseIso($this->msDue);

        if ($due === false) {
            $this->addError('msDue', __('task.date_invalid'));

            return;
        }

        $data = [
            'title' => $this->msTitle,
            'description' => $this->msDescription,
            'due_at' => $due?->endOfDay(),
        ];

        try {
            if ($this->editingMilestoneId !== null) {
                $milestone = $this->findMilestone($this->editingMilestoneId);
                $this->authorize('update', $milestone);
                $service->update($milestone, $data);
            } else {
                $this->authorize('create', [Milestone::class, $this->event]);
                $service->create($this->event, $data);
            }
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->resetMilestoneForm();
    }

    public function setMilestoneStatus(int $id, string $status, MilestoneService $service): void
    {
        $this->clearMessages();

        $milestone = $this->findMilestone($id);
        $this->authorize('update', $milestone);

        $target = MilestoneStatus::tryFrom($status);

        if ($target === null) {
            $this->error = __('task.invalid_value');

            return;
        }

        $service->setStatus($milestone, $target);
    }

    public function deleteMilestone(int $id, MilestoneService $service): void
    {
        $this->clearMessages();

        $milestone = $this->findMilestone($id);
        $this->authorize('delete', $milestone);

        $service->delete($milestone);
    }

    // ===================================================================== render

    public function render(TaskService $service)
    {
        $user = Auth::user();
        $event = $this->event;

        $isManager = $user->can('update', $event);
        $headIds = $event->committees()->where('head_user_id', $user->id)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $role = $event->roleOf($user);
        $nonViewer = $role !== null && $role !== EventRole::Viewer;
        $closed = in_array($event->status, [EventStatus::Archived, EventStatus::Cancelled], true);

        $committees = $event->committees()->get();
        $members = $event->members()->get()->reject(fn ($m) => $m->pivot->role === EventRole::Viewer->value)->values();

        $limit = (int) config('tasks.list_limit', 300);
        $tasks = collect();
        $tasksTruncated = false;

        if ($this->mode !== 'calendar') {
            $tasks = $this->sortedQuery()
                ->with(['assignee', 'committee', 'parent', 'creator'])
                ->limit($limit + 1)
                ->get();

            if ($tasks->count() > $limit) {
                $tasksTruncated = true;
                $tasks = $tasks->take($limit);
            }
        }

        $data = [
            'isManager' => $isManager,
            'canCreate' => ! $closed && ($isManager || $headIds !== []),
            'closed' => $closed,
            'headIds' => $headIds,
            'nonViewer' => $nonViewer,
            'committees' => $committees,
            'formCommittees' => $isManager ? $committees : $committees->whereIn('id', $headIds)->values(),
            'members' => $members,
            'tasks' => $tasks,
            'tasksTruncated' => $tasksTruncated,
            'progress' => $service->progressForEvent($event),
            'overdueCount' => Task::query()->where('event_id', $event->id)->overdue()->count(),
            'blockedCount' => Task::query()->where('event_id', $event->id)->where('status', TaskStatus::Blocked->value)->count(),
            'milestones' => $event->milestones()->ordered()->get(),
            'canManageMilestones' => ! $closed && $user->can('create', [Milestone::class, $event]),
            'statuses' => TaskStatus::cases(),
            'phases' => TaskPhase::cases(),
            'modules' => TaskModule::cases(),
            'priorities' => TaskPriority::cases(),
            'calendarEvents' => [],
            'undated' => collect(),
            'open' => null,
        ];

        if ($this->mode === 'calendar') {
            $data['calendarEvents'] = $this->calendarEvents();
            $data['undated'] = $this->filteredQuery()->whereNull('due_at')->orderBy('position')->limit(50)->get();
        }

        if ($this->openId !== null) {
            $data['open'] = $this->openData($user, $closed);
        }

        return view('livewire.events.event-tasks', $data);
    }

    // ================================================================== internals

    /**
     * رویدادهای تقویم (وظایفِ فیلترشده‌ی دارای موعد + نقاط عطف) به‌صورت آرایه‌ی ساده برای Alpine.
     * تاریخ‌ها ISO میلادی‌اند؛ تبدیل به شمسی فقط در مرورگر (jalali.js) انجام می‌شود.
     *
     * @return list<array<string, mixed>>
     */
    private function calendarEvents(): array
    {
        $events = [];

        $tasks = $this->filteredQuery()
            ->whereNotNull('due_at')
            ->with('assignee')
            ->orderBy('due_at')
            ->limit(1500)
            ->get();

        foreach ($tasks as $task) {
            $events[] = [
                'id' => $task->id,
                'kind' => 'task',
                'title' => $task->title,
                'date' => $task->due_at->toDateString(),
                'status' => $task->status->value,
                'statusLabel' => $task->status->label(),
                'priority' => $task->priority->value,
                'priorityLabel' => $task->priority->label(),
                'overdue' => $task->isOverdue(),
                'who' => $task->assignee?->name,
            ];
        }

        foreach ($this->event->milestones()->whereNotNull('due_at')->get() as $milestone) {
            $events[] = [
                'id' => $milestone->id,
                'kind' => 'milestone',
                'title' => $milestone->title,
                'date' => $milestone->due_at->toDateString(),
                'status' => $milestone->status->value,
                'statusLabel' => $milestone->status->label(),
                'overdue' => $milestone->isOverdue(),
            ];
        }

        return $events;
    }

    /** @return array<string, mixed>|null */
    private function openData(User $user, bool $closed): ?array
    {
        $task = $this->event->tasks()
            ->whereKey($this->openId)
            ->with(['assignee', 'committee', 'parent', 'creator',
                'subtasks.assignee', 'prerequisites', 'dependents',
                'comments.user', 'attachments.uploader'])
            ->first();

        if ($task === null || ! $user->can('view', $task)) {
            $this->openId = null;

            return null;
        }

        $taken = $task->prerequisites->pluck('id')->push($task->id)->all();

        return [
            'task' => $task,
            'canUpdate' => ! $closed && $user->can('update', $task),
            'canDelete' => ! $closed && $user->can('delete', $task),
            'canTransition' => ! $closed && $user->can('transition', $task),
            'canDeps' => ! $closed && $user->can('manageDependencies', $task),
            'canComment' => $user->can('comment', $task),
            'canAttach' => ! $closed && $user->can('attach', $task),
            'canAddSubtask' => ! $closed && ! $task->isSubtask() && $user->can('create', [Task::class, $this->event, $task->committee]),
            'unmet' => $task->unmetPrerequisites()->pluck('tasks.id')->all(),
            'candidates' => $this->event->tasks()->whereNotIn('id', $taken)->orderBy('title')->limit(200)->get(['id', 'title']),
            'maxMb' => intdiv((int) config('tasks.attachments.max_kb', 5120), 1024),
        ];
    }

    /**
     * @return Builder<Task>
     */
    private function filteredQuery(): Builder
    {
        $q = Task::query()->where('event_id', $this->event->id);

        if ($this->fCommittee === 'none') {
            $q->whereNull('committee_id');
        } elseif ($this->fCommittee !== '') {
            $q->where('committee_id', (int) $this->fCommittee);
        }

        if ($this->fStatus === 'open') {
            $q->open();
        } elseif ($this->fStatus !== '' && TaskStatus::tryFrom($this->fStatus) !== null) {
            $q->where('status', $this->fStatus);
        }

        if ($this->fAssignee === 'none') {
            $q->whereNull('assignee_id');
        } elseif ($this->fAssignee !== '') {
            $q->where('assignee_id', (int) $this->fAssignee);
        }

        if ($this->fMine) {
            $q->where('assignee_id', Auth::id());
        }

        if ($this->fPhase !== '' && TaskPhase::tryFrom($this->fPhase) !== null) {
            $q->where('phase', $this->fPhase);
        }

        match ($this->fDue) {
            'overdue' => $q->overdue(),
            'week' => $q->open()->whereNotNull('due_at')->whereBetween('due_at', [now(), now()->addDays(7)->endOfDay()]),
            'none' => $q->whereNull('due_at'),
            default => null,
        };

        $search = trim($this->fSearch);
        if ($search !== '') {
            $q->where('title', 'like', '%'.addcslashes($search, '%_\\').'%');
        }

        return $q;
    }

    /**
     * @return Builder<Task>
     */
    private function sortedQuery(): Builder
    {
        $q = $this->filteredQuery();

        return match ($this->sort) {
            'due' => $q->orderByRaw('due_at is null')->orderBy('due_at')->orderBy('id'),
            'priority' => $q->orderByRaw("case priority when 'urgent' then 4 when 'high' then 3 when 'medium' then 2 else 1 end desc")->orderBy('id'),
            'status' => $q->orderByRaw("case status when 'doing' then 1 when 'blocked' then 2 when 'todo' then 3 when 'done' then 4 else 5 end")->orderBy('id'),
            default => $q->ordered(),
        };
    }

    /**
     * تاریخ ISO میلادی (YYYY-MM-DD) از انتخاب‌گر. خالی → null؛ نامعتبر → false.
     */
    private function parseIso(string $value): Carbon|false|null
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return false;
        }

        return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
    }

    private function isClosed(): bool
    {
        return in_array($this->event->status, [EventStatus::Archived, EventStatus::Cancelled], true);
    }

    private function canCreateAny(): bool
    {
        $user = Auth::user();

        return $user->can('update', $this->event)
            || $this->event->committees()->where('head_user_id', $user->id)->exists();
    }

    private function findTask(int $id): Task
    {
        return $this->event->tasks()->whereKey($id)->firstOrFail();
    }

    private function currentTask(): Task
    {
        abort_if($this->openId === null, 404);

        return $this->findTask($this->openId);
    }

    private function findCommittee(int $id): Committee
    {
        return $this->event->committees()->whereKey($id)->firstOrFail();
    }

    private function findMilestone(int $id): Milestone
    {
        return $this->event->milestones()->whereKey($id)->firstOrFail();
    }

    private function clearMessages(): void
    {
        $this->error = null;
        $this->notice = null;
    }

    private function resetForm(): void
    {
        $this->reset([
            'showForm', 'editingId', 'formParentId', 'title', 'description', 'committeeId',
            'assigneeId', 'startAt', 'dueAt', 'hours',
        ]);
        $this->phase = 'pre';
        $this->module = 'operations';
        $this->priority = 'medium';
        $this->resetValidation();
    }

    private function resetMilestoneForm(): void
    {
        $this->reset(['showMilestoneForm', 'editingMilestoneId', 'msTitle', 'msDescription', 'msDue']);
        $this->resetValidation();
    }
}
