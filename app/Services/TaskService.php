<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EventRole;
use App\Enums\TaskModule;
use App\Enums\TaskPhase;
use App\Enums\TaskPriority;
use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Exceptions\LimitExceededException;
use App\Models\Committee;
use App\Models\Event;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\TaskComment;
use App\Models\User;
use App\Support\DependencyGraph;
use App\Support\LogsActivity;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Step 08a — task business logic.
 *
 * Authorization is NOT done here (project rule): callers (Livewire components,
 * controllers) authorize through TaskPolicy first. This service guarantees the
 * data rules: tenant consistency (everything referenced belongs to the same
 * event), plan limits, one-level subtasks, acyclic dependencies and the
 * automatic `blocked` state.
 */
class TaskService
{
    use LogsActivity;

    public const MAX_TITLE_LENGTH = 200;

    public const MAX_COMMENT_LENGTH = 2000;

    public function __construct(
        private EntitlementService $entitlement,
    ) {}

    public function count(Event $event): int
    {
        // soft-deleted tasks are excluded by the SoftDeletes scope
        return $event->tasks()->count();
    }

    // ------------------------------------------------------------------ CRUD

    /**
     * @param  array<string, mixed>  $data  title, description, phase, module, priority, committee_id,
     *                                      assignee_id, parent_id, start_at, due_at, estimated_hours,
     *                                      source, ai_recommendation_id
     *
     * @throws LimitExceededException
     * @throws InvalidArgumentException
     */
    public function create(Event $event, User $actor, array $data): Task
    {
        $title = $this->validTitle($data['title'] ?? '');

        $this->entitlement->assertWithinLimit($actor, 'tasks_per_event', $this->count($event));

        $parent = $this->resolveParent($event, $data['parent_id'] ?? null);
        // a subtask inherits the parent's committee unless one is given explicitly
        $committee = $this->resolveCommittee(
            $event,
            array_key_exists('committee_id', $data) ? $data['committee_id'] : $parent?->committee_id,
        );
        $assignee = $this->resolveAssignee($event, $data['assignee_id'] ?? null);
        [$startAt, $dueAt] = $this->resolveDates($data['start_at'] ?? null, $data['due_at'] ?? null);

        return DB::transaction(function () use ($event, $actor, $data, $title, $parent, $committee, $assignee, $startAt, $dueAt) {
            $task = $event->tasks()->create([
                'committee_id' => $committee?->id,
                'parent_id' => $parent?->id,
                'created_by' => $actor->id,
                'title' => $title,
                'description' => $this->nullableText($data['description'] ?? null),
                'phase' => $this->enumValue(TaskPhase::class, $data['phase'] ?? null) ?? TaskPhase::Pre,
                'module' => $this->enumValue(TaskModule::class, $data['module'] ?? null) ?? TaskModule::Operations,
                'priority' => $this->enumValue(TaskPriority::class, $data['priority'] ?? null) ?? TaskPriority::Medium,
                'assignee_id' => $assignee?->id,
                'status' => TaskStatus::Todo,
                'start_at' => $startAt,
                'due_at' => $dueAt,
                'estimated_hours' => $this->resolveHours($data['estimated_hours'] ?? null),
                'source' => $this->enumValue(TaskSource::class, $data['source'] ?? null) ?? TaskSource::Manual,
                'ai_recommendation_id' => $data['ai_recommendation_id'] ?? null,
                'position' => ((int) $event->tasks()->max('position')) + 1,
            ]);

            self::logActivity('task.created', $task, $event->id, [
                'title' => $task->title,
                'committee_id' => $task->committee_id,
                'parent_id' => $task->parent_id,
            ]);

            return $task->refresh();
        });
    }

    /**
     * Updates descriptive fields only. Status changes go through transition().
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Task $task, array $data): Task
    {
        $event = $task->event;
        $payload = [];

        if (array_key_exists('title', $data)) {
            $payload['title'] = $this->validTitle($data['title'] ?? '');
        }
        if (array_key_exists('description', $data)) {
            $payload['description'] = $this->nullableText($data['description']);
        }

        foreach (['phase' => TaskPhase::class, 'module' => TaskModule::class, 'priority' => TaskPriority::class] as $key => $enum) {
            if (array_key_exists($key, $data) && ($case = $this->enumValue($enum, $data[$key])) !== null) {
                $payload[$key] = $case;
            }
        }

        if (array_key_exists('committee_id', $data)) {
            $payload['committee_id'] = $this->resolveCommittee($event, $data['committee_id'])?->id;
        }
        if (array_key_exists('assignee_id', $data)) {
            $payload['assignee_id'] = $this->resolveAssignee($event, $data['assignee_id'])?->id;
        }
        if (array_key_exists('parent_id', $data)) {
            $payload['parent_id'] = $this->resolveParent($event, $data['parent_id'], $task)?->id;
        }
        if (array_key_exists('estimated_hours', $data)) {
            $payload['estimated_hours'] = $this->resolveHours($data['estimated_hours']);
        }
        if (array_key_exists('start_at', $data) || array_key_exists('due_at', $data)) {
            [$startAt, $dueAt] = $this->resolveDates(
                array_key_exists('start_at', $data) ? $data['start_at'] : $task->start_at,
                array_key_exists('due_at', $data) ? $data['due_at'] : $task->due_at,
            );
            $payload['start_at'] = $startAt;
            $payload['due_at'] = $dueAt;
        }

        if ($payload === []) {
            return $task;
        }

        $task->update($payload);

        $changes = array_values(array_diff(array_keys($task->getChanges()), ['updated_at']));
        if ($changes !== []) {
            self::logActivity('task.updated', $task, $task->event_id, ['changes' => $changes]);
        }

        return $task->refresh();
    }

    /**
     * Soft-deletes the task and its subtasks, then re-evaluates everything that
     * depended on them (a deleted prerequisite no longer blocks anything).
     */
    public function delete(Task $task): void
    {
        DB::transaction(function () use ($task) {
            $subtasks = $task->subtasks()->get();
            $deletedIds = $subtasks->pluck('id')->push($task->id)->map(fn ($id) => (int) $id)->all();

            // collect dependents BEFORE deleting (the relation hides trashed rows afterwards)
            $dependentIds = DB::table('task_dependencies')
                ->whereIn('depends_on_task_id', $deletedIds)
                ->pluck('task_id')
                ->map(fn ($id) => (int) $id)
                ->diff($deletedIds)
                ->unique()
                ->values();

            foreach ($subtasks as $subtask) {
                $subtask->delete();
            }

            self::logActivity('task.deleted', $task, $task->event_id, [
                'title' => $task->title,
                'subtasks' => $subtasks->count(),
            ]);

            $task->delete();

            Task::query()->whereIn('id', $dependentIds)->get()
                ->each(fn (Task $dependent) => $this->syncBlockedState($dependent));
        });
    }

    // ---------------------------------------------------------------- status

    /**
     * Manual status change.
     *  - `blocked` is system-managed and cannot be set by hand.
     *  - while prerequisites are unfinished only `cancelled` is possible.
     *  - leaving/entering `done` maintains completed_at and re-evaluates dependents.
     *
     * @throws InvalidArgumentException
     */
    public function transition(Task $task, TaskStatus $to): Task
    {
        $from = $task->status;

        if ($from === $to) {
            return $task;
        }

        if ($to === TaskStatus::Blocked) {
            throw new InvalidArgumentException(__('task.blocked_is_automatic'));
        }

        if ($to !== TaskStatus::Cancelled && $task->hasUnmetPrerequisites()) {
            throw new InvalidArgumentException(__('task.blocked_by_prerequisites'));
        }

        DB::transaction(function () use ($task, $from, $to) {
            $task->update([
                'status' => $to,
                'completed_at' => $to === TaskStatus::Done ? now() : null,
            ]);

            self::logActivity('task.status_changed', $task, $task->event_id, [
                'from' => $from?->value,
                'to' => $to->value,
            ]);

            // only `done` releases dependents, so only they can change state
            $task->dependents()->get()
                ->each(fn (Task $dependent) => $this->syncBlockedState($dependent));
        });

        return $task->refresh();
    }

    /**
     * Re-evaluate the automatic `blocked` state of one task:
     *  - open (todo/doing) + unfinished prerequisites  → blocked
     *  - blocked + no unfinished prerequisites         → todo
     * done/cancelled tasks are never touched.
     */
    public function syncBlockedState(Task $task): Task
    {
        $task->refresh();

        $unmet = $task->hasUnmetPrerequisites();

        if ($unmet && in_array($task->status, [TaskStatus::Todo, TaskStatus::Doing], true)) {
            $task->update(['status' => TaskStatus::Blocked]);
            self::logActivity('task.blocked', $task, $task->event_id, ['automatic' => true]);
        } elseif (! $unmet && $task->status === TaskStatus::Blocked) {
            $task->update(['status' => TaskStatus::Todo]);
            self::logActivity('task.unblocked', $task, $task->event_id, ['automatic' => true]);
        }

        return $task;
    }

    // ---------------------------------------------------------- dependencies

    /**
     * "$task cannot start until $dependsOn is done" (finish-to-start).
     *
     * @throws InvalidArgumentException
     */
    public function addDependency(Task $task, Task $dependsOn): void
    {
        if ($task->is($dependsOn)) {
            throw new InvalidArgumentException(__('task.dependency_self'));
        }

        if ((int) $task->event_id !== (int) $dependsOn->event_id) {
            throw new InvalidArgumentException(__('task.dependency_other_event'));
        }

        if ($task->trashed() || $dependsOn->trashed()) {
            throw new InvalidArgumentException(__('task.dependency_invalid'));
        }

        if ($task->prerequisites()->whereKey($dependsOn->id)->exists()) {
            throw new InvalidArgumentException(__('task.dependency_exists'));
        }

        if ($this->wouldCreateCycle($task, $dependsOn)) {
            throw new InvalidArgumentException(__('task.dependency_cycle'));
        }

        DB::transaction(function () use ($task, $dependsOn) {
            $task->prerequisites()->attach($dependsOn->id, ['type' => Task::DEPENDENCY_FINISH_TO_START]);

            self::logActivity('task.dependency_added', $task, $task->event_id, [
                'depends_on' => $dependsOn->id,
            ]);

            $this->syncBlockedState($task);
        });
    }

    public function removeDependency(Task $task, Task $dependsOn): void
    {
        DB::transaction(function () use ($task, $dependsOn) {
            $removed = $task->prerequisites()->detach($dependsOn->id);

            if ($removed > 0) {
                self::logActivity('task.dependency_removed', $task, $task->event_id, [
                    'depends_on' => $dependsOn->id,
                ]);
            }

            $this->syncBlockedState($task);
        });
    }

    public function wouldCreateCycle(Task $task, Task $dependsOn): bool
    {
        return DependencyGraph::wouldCreateCycle(
            $this->dependencyGraph((int) $task->event_id),
            (int) $task->id,
            (int) $dependsOn->id,
        );
    }

    /**
     * Prerequisite adjacency map of one event (live tasks only).
     *
     * @return array<int, list<int>>
     */
    private function dependencyGraph(int $eventId): array
    {
        $rows = DB::table('task_dependencies as d')
            ->join('tasks as t', 't.id', '=', 'd.task_id')
            ->join('tasks as p', 'p.id', '=', 'd.depends_on_task_id')
            ->where('t.event_id', $eventId)
            ->whereNull('t.deleted_at')
            ->whereNull('p.deleted_at')
            ->get(['d.task_id', 'd.depends_on_task_id']);

        $graph = [];
        foreach ($rows as $row) {
            $graph[(int) $row->task_id][] = (int) $row->depends_on_task_id;
        }

        return $graph;
    }

    // -------------------------------------------------------------- comments

    public function addComment(Task $task, User $author, string $body): TaskComment
    {
        $body = trim($body);

        if ($body === '') {
            throw new InvalidArgumentException(__('task.comment_required'));
        }
        if (mb_strlen($body) > self::MAX_COMMENT_LENGTH) {
            throw new InvalidArgumentException(__('task.comment_too_long', ['max' => self::MAX_COMMENT_LENGTH]));
        }

        $comment = $task->comments()->create([
            'user_id' => $author->id,
            'body' => $body,
        ]);

        self::logActivity('task.commented', $task, $task->event_id, ['comment_id' => $comment->id]);

        return $comment;
    }

    public function deleteComment(TaskComment $comment): void
    {
        $task = $comment->task;

        $comment->delete();

        self::logActivity('task.comment_deleted', $task, $task->event_id, ['comment_id' => $comment->id]);
    }

    // ----------------------------------------------------------- attachments

    /**
     * Stores a file on the private disk. Validates size, type (extension AND the
     * server-detected MIME type) and the event's total storage limit.
     *
     * @throws InvalidArgumentException
     * @throws LimitExceededException
     */
    public function addAttachment(Task $task, User $actor, UploadedFile $file): TaskAttachment
    {
        $config = (array) config('tasks.attachments');

        if (! $file->isValid()) {
            throw new InvalidArgumentException(__('task.attachment_invalid'));
        }

        $size = (int) $file->getSize();
        if ($size <= 0) {
            throw new InvalidArgumentException(__('task.attachment_invalid'));
        }

        $maxKb = (int) ($config['max_kb'] ?? 5120);
        if ($size > $maxKb * 1024) {
            throw new InvalidArgumentException(__('task.attachment_too_large', ['max' => intdiv($maxKb, 1024)]));
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $mime = (string) $file->getMimeType();
        $accepted = $config['allowed'][$extension] ?? null;

        if (! is_array($accepted) || ! in_array($mime, $accepted, true)) {
            throw new InvalidArgumentException(__('task.attachment_type_not_allowed'));
        }

        $this->assertStorageWithinLimit($task, $actor, $size);

        $disk = (string) ($config['disk'] ?? 'local');
        $path = $file->storeAs(
            "task-attachments/{$task->event_id}/{$task->id}",
            Str::uuid()->toString().'.'.$extension,
            ['disk' => $disk],
        );

        if ($path === false) {
            throw new InvalidArgumentException(__('task.attachment_store_failed'));
        }

        try {
            $attachment = $task->attachments()->create([
                'uploaded_by' => $actor->id,
                'disk' => $disk,
                'path' => $path,
                'original_name' => $this->safeFileName($file->getClientOriginalName(), $extension),
                'mime_type' => $mime,
                'size' => $size,
            ]);
        } catch (Throwable $e) {
            Storage::disk($disk)->delete($path);

            throw $e;
        }

        self::logActivity('task.attachment_added', $task, $task->event_id, [
            'attachment_id' => $attachment->id,
            'name' => $attachment->original_name,
            'size' => $size,
        ]);

        return $attachment;
    }

    public function removeAttachment(TaskAttachment $attachment): void
    {
        $task = $attachment->task;

        DB::transaction(function () use ($attachment, $task) {
            self::logActivity('task.attachment_removed', $task, $task->event_id, [
                'attachment_id' => $attachment->id,
                'name' => $attachment->original_name,
            ]);

            $attachment->delete();
        });

        Storage::disk($attachment->disk)->delete($attachment->path);
    }

    /**
     * Bytes of attachments already stored for the event (all its tasks).
     */
    public function storageUsedBytes(Event $event): int
    {
        return (int) DB::table('task_attachments as a')
            ->join('tasks as t', 't.id', '=', 'a.task_id')
            ->where('t.event_id', $event->id)
            ->sum('a.size');
    }

    private function assertStorageWithinLimit(Task $task, User $actor, int $newBytes): void
    {
        $limitMb = $this->entitlement->limit($actor, 'attachment_mb_per_event');

        if ($limitMb === null) {
            return;
        }

        $used = $this->storageUsedBytes($task->event);

        if ($used + $newBytes > $limitMb * 1024 * 1024) {
            throw new LimitExceededException(
                'attachment_mb_per_event',
                $limitMb,
                __('account.limit_exceeded_detail', [
                    'limit' => __('account.limits.attachment_mb_per_event'),
                    'max' => $limitMb,
                ]),
            );
        }
    }

    // -------------------------------------------------------------- progress

    /**
     * Progress of the whole event.
     *
     * Rule: only LEAF tasks count (a task with subtasks is represented by them,
     * so nothing is counted twice) and cancelled tasks are ignored.
     *
     * @return array{total: int, done: int, percent: int}
     */
    public function progressForEvent(Event $event): array
    {
        return $this->progressOf(Task::query()->where('event_id', $event->id));
    }

    /**
     * @return array{total: int, done: int, percent: int}
     */
    public function progressForCommittee(Committee $committee): array
    {
        return $this->progressOf(Task::query()->where('committee_id', $committee->id));
    }

    /**
     * @param  Builder<Task>  $tasks
     * @return array{total: int, done: int, percent: int}
     */
    private function progressOf(Builder $tasks): array
    {
        $leaves = $tasks
            ->whereDoesntHave('subtasks')
            ->where('status', '!=', TaskStatus::Cancelled->value);

        $total = (clone $leaves)->count();
        $done = (clone $leaves)->where('status', TaskStatus::Done->value)->count();

        return [
            'total' => $total,
            'done' => $done,
            'percent' => $total > 0 ? (int) round($done * 100 / $total) : 0,
        ];
    }

    // --------------------------------------------------------------- helpers

    private function validTitle(mixed $title): string
    {
        $title = trim((string) $title);

        if ($title === '') {
            throw new InvalidArgumentException(__('task.title_required'));
        }
        if (mb_strlen($title) > self::MAX_TITLE_LENGTH) {
            throw new InvalidArgumentException(__('task.title_too_long', ['max' => self::MAX_TITLE_LENGTH]));
        }

        return $title;
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @param  class-string<BackedEnum>  $enum
     * @return BackedEnum|null null when no value was given; throws when the value is invalid
     */
    private function enumValue(string $enum, mixed $value): ?BackedEnum
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof $enum) {
            return $value;
        }

        $case = is_string($value) || is_int($value) ? $enum::tryFrom($value) : null;

        if ($case === null) {
            throw new InvalidArgumentException(__('task.invalid_value'));
        }

        return $case;
    }

    private function resolveHours(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value) || (float) $value < 0 || (float) $value > 9999.99) {
            throw new InvalidArgumentException(__('task.hours_invalid'));
        }

        return number_format((float) $value, 2, '.', '');
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function resolveDates(mixed $start, mixed $due): array
    {
        $startAt = $this->parseDate($start);
        $dueAt = $this->parseDate($due);

        if ($startAt !== null && $dueAt !== null && $dueAt->lt($startAt)) {
            throw new InvalidArgumentException(__('task.due_before_start'));
        }

        return [$startAt, $dueAt];
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value);
        }

        try {
            return Carbon::parse((string) $value);
        } catch (Throwable) {
            throw new InvalidArgumentException(__('task.date_invalid'));
        }
    }

    /**
     * Parent must belong to the same event and be a top-level task (one level only).
     * When re-parenting an existing task, that task must not have subtasks itself.
     */
    private function resolveParent(Event $event, mixed $id, ?Task $self = null): ?Task
    {
        if ($id === null || $id === '') {
            return null;
        }

        $parent = $event->tasks()->whereKey($id)->first();

        if ($parent === null || ($self !== null && $parent->is($self))) {
            throw new InvalidArgumentException(__('task.parent_invalid'));
        }

        if ($parent->parent_id !== null) {
            throw new InvalidArgumentException(__('task.subtask_depth'));
        }

        if ($self !== null && $self->subtasks()->exists()) {
            throw new InvalidArgumentException(__('task.subtask_has_children'));
        }

        return $parent;
    }

    private function resolveCommittee(Event $event, mixed $id): ?Committee
    {
        if ($id === null || $id === '') {
            return null;
        }

        $committee = $event->committees()->whereKey($id)->first();

        if ($committee === null) {
            throw new InvalidArgumentException(__('task.committee_invalid'));
        }

        return $committee;
    }

    /**
     * Assignee must be a member of THE SAME event and not a read-only viewer.
     */
    private function resolveAssignee(Event $event, mixed $id): ?User
    {
        if ($id === null || $id === '') {
            return null;
        }

        $user = $event->members()->where('users.id', $id)->first();

        if ($user === null) {
            throw new InvalidArgumentException(__('task.assignee_invalid'));
        }

        if ($user->pivot->role === EventRole::Viewer->value) {
            throw new InvalidArgumentException(__('task.assignee_viewer'));
        }

        return $user;
    }

    private function safeFileName(string $name, string $extension): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = (string) preg_replace('/[\x00-\x1F\x7F]+/u', '', $name);
        $name = trim(Str::limit($name, 200, ''));

        return $name !== '' ? $name : 'file.'.$extension;
    }
}
