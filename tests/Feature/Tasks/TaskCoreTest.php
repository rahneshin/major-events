<?php

use App\Enums\EventRole;
use App\Enums\TaskModule;
use App\Enums\TaskPhase;
use App\Enums\TaskPriority;
use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Exceptions\LimitExceededException;
use App\Models\ActivityLog;
use App\Models\Task;
use App\Models\User;
use App\Services\CommitteeService;
use App\Services\EventMemberService;
use App\Services\TaskService;

// ---------------------------------------------------------------- creation

it('creates a task with sensible defaults and logs it', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);

    $task = tk_task($event, $owner, ['title' => '  رزرو سالن  ']);

    expect($task->title)->toBe('رزرو سالن')
        ->and($task->uuid)->not->toBeEmpty()
        ->and($task->status)->toBe(TaskStatus::Todo)
        ->and($task->phase)->toBe(TaskPhase::Pre)
        ->and($task->module)->toBe(TaskModule::Operations)
        ->and($task->priority)->toBe(TaskPriority::Medium)
        ->and($task->source)->toBe(TaskSource::Manual)
        ->and($task->created_by)->toBe($owner->id)
        ->and($task->position)->toBe(1)
        ->and(ActivityLog::query()->where('action', 'task.created')->where('subject_id', $task->id)->exists())->toBeTrue();
});

it('numbers task positions per event', function () {
    $owner = User::factory()->organizational()->create();
    $a = tk_event($owner);
    $b = tk_event($owner);

    tk_task($a, $owner);
    $second = tk_task($a, $owner);
    $otherEvent = tk_task($b, $owner);

    expect($second->position)->toBe(2)
        ->and($otherEvent->position)->toBe(1);
});

it('requires a title and caps its length', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);

    expect(fn () => tk_task($event, $owner, ['title' => '   ']))->toThrow(InvalidArgumentException::class)
        ->and(fn () => tk_task($event, $owner, ['title' => str_repeat('ا', 201)]))->toThrow(InvalidArgumentException::class);
});

it('rejects invalid enum values instead of silently defaulting', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);

    expect(fn () => tk_task($event, $owner, ['priority' => 'nope']))->toThrow(InvalidArgumentException::class)
        ->and(fn () => tk_task($event, $owner, ['phase' => 'nope']))->toThrow(InvalidArgumentException::class)
        ->and(fn () => tk_task($event, $owner, ['module' => 'nope']))->toThrow(InvalidArgumentException::class);
});

// ----------------------------------------------------------------- limits

it('enforces tasks_per_event for individual accounts and frees a slot on delete', function () {
    config(['plans.individual.limits.tasks_per_event' => 3]);

    $owner = User::factory()->individual()->create();
    $event = tk_event($owner);
    $svc = app(TaskService::class);

    $first = tk_task($event, $owner);
    tk_task($event, $owner);
    tk_task($event, $owner);

    expect(fn () => tk_task($event, $owner))->toThrow(LimitExceededException::class);

    $svc->delete($first);

    expect($svc->count($event))->toBe(2)
        ->and(tk_task($event, $owner))->toBeInstanceOf(Task::class);
});

it('does not limit tasks for organizational accounts', function () {
    config(['plans.individual.limits.tasks_per_event' => 1]);

    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);

    foreach (range(1, 5) as $i) {
        tk_task($event, $owner);
    }

    expect(app(TaskService::class)->count($event))->toBe(5);
});

// ---------------------------------------------------------- tenant checks

it('rejects a committee that belongs to another event', function () {
    $owner = User::factory()->organizational()->create();
    $a = tk_event($owner);
    $b = tk_event($owner);
    $foreign = app(CommitteeService::class)->create($b, $owner, ['name' => 'خارجی']);

    expect(fn () => tk_task($a, $owner, ['committee_id' => $foreign->id]))
        ->toThrow(InvalidArgumentException::class);
});

it('only accepts assignees who are non-viewer members of the same event', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $other = tk_event($owner);

    $member = User::factory()->create();
    $viewer = User::factory()->create();
    $stranger = User::factory()->create();
    $memberOfOtherEvent = User::factory()->create();

    tk_join($event, $member);
    tk_join($event, $viewer, EventRole::Viewer);
    tk_join($other, $memberOfOtherEvent);

    expect(tk_task($event, $owner, ['assignee_id' => $member->id])->assignee_id)->toBe($member->id)
        ->and(fn () => tk_task($event, $owner, ['assignee_id' => $viewer->id]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => tk_task($event, $owner, ['assignee_id' => $stranger->id]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => tk_task($event, $owner, ['assignee_id' => $memberOfOtherEvent->id]))->toThrow(InvalidArgumentException::class);
});

it('rejects a parent from another event', function () {
    $owner = User::factory()->organizational()->create();
    $a = tk_event($owner);
    $b = tk_event($owner);
    $foreignParent = tk_task($b, $owner);

    expect(fn () => tk_task($a, $owner, ['parent_id' => $foreignParent->id]))
        ->toThrow(InvalidArgumentException::class);
});

// --------------------------------------------------------------- subtasks

it('allows only one level of subtasks and inherits the parent committee', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $committee = app(CommitteeService::class)->create($event, $owner, ['name' => 'فنی']);

    $parent = tk_task($event, $owner, ['committee_id' => $committee->id]);
    $child = tk_task($event, $owner, ['parent_id' => $parent->id]);

    expect($child->parent_id)->toBe($parent->id)
        ->and($child->committee_id)->toBe($committee->id)
        ->and($child->isSubtask())->toBeTrue()
        ->and(fn () => tk_task($event, $owner, ['parent_id' => $child->id]))->toThrow(InvalidArgumentException::class);
});

it('does not let a task with subtasks become a subtask, or its own parent', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $svc = app(TaskService::class);

    $parent = tk_task($event, $owner);
    tk_task($event, $owner, ['parent_id' => $parent->id]);
    $other = tk_task($event, $owner);

    expect(fn () => $svc->update($parent, ['parent_id' => $other->id]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $svc->update($other, ['parent_id' => $other->id]))->toThrow(InvalidArgumentException::class);
});

it('soft-deletes subtasks together with their parent', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);

    $parent = tk_task($event, $owner);
    $child = tk_task($event, $owner, ['parent_id' => $parent->id]);

    app(TaskService::class)->delete($parent);

    $ids = [$parent->id, $child->id];

    expect(Task::query()->whereKey($ids)->count())->toBe(0)
        ->and(Task::withTrashed()->whereKey($ids)->count())->toBe(2)
        ->and(ActivityLog::query()->where('action', 'task.deleted')->exists())->toBeTrue();
});

// ------------------------------------------------------------------ dates

it('validates dates and their order', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);

    $task = tk_task($event, $owner, ['start_at' => '2026-12-01 09:00', 'due_at' => '2026-12-10 09:00']);

    expect($task->start_at->toDateString())->toBe('2026-12-01')
        ->and($task->due_at->toDateString())->toBe('2026-12-10')
        ->and(fn () => tk_task($event, $owner, ['start_at' => '2026-12-10', 'due_at' => '2026-12-09']))->toThrow(InvalidArgumentException::class)
        ->and(fn () => tk_task($event, $owner, ['due_at' => 'not a date']))->toThrow(InvalidArgumentException::class);
});

it('flags only open tasks past their due date as overdue', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $svc = app(TaskService::class);

    $late = tk_task($event, $owner, ['due_at' => now()->subDay()]);
    $onTime = tk_task($event, $owner, ['due_at' => now()->addDay()]);
    $doneLate = tk_task($event, $owner, ['due_at' => now()->subDay()]);
    $svc->transition($doneLate, TaskStatus::Done);

    expect($late->isOverdue())->toBeTrue()
        ->and($onTime->isOverdue())->toBeFalse()
        ->and($doneLate->fresh()->isOverdue())->toBeFalse()
        ->and(Task::query()->where('event_id', $event->id)->overdue()->pluck('id')->all())->toBe([$late->id]);
});

// ----------------------------------------------------------------- update

it('updates descriptive fields but never the status', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $svc = app(TaskService::class);
    $task = tk_task($event, $owner);

    $updated = $svc->update($task, [
        'title' => 'عنوان جدید',
        'priority' => 'urgent',
        'estimated_hours' => '2.5',
        'status' => 'done', // must be ignored: status changes go through transition()
    ]);

    expect($updated->title)->toBe('عنوان جدید')
        ->and($updated->priority)->toBe(TaskPriority::Urgent)
        ->and((float) $updated->estimated_hours)->toBe(2.5)
        ->and($updated->status)->toBe(TaskStatus::Todo)
        ->and(ActivityLog::query()->where('action', 'task.updated')->where('subject_id', $task->id)->exists())->toBeTrue();
});

it('validates updated values', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $svc = app(TaskService::class);
    $task = tk_task($event, $owner);

    expect(fn () => $svc->update($task, ['title' => '']))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $svc->update($task, ['priority' => 'nope']))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $svc->update($task, ['estimated_hours' => '-1']))->toThrow(InvalidArgumentException::class);
});

// ------------------------------------------------------------ status/time

it('maintains completed_at and logs status changes', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $svc = app(TaskService::class);
    $task = tk_task($event, $owner);

    $svc->transition($task, TaskStatus::Doing);
    expect($task->fresh()->completed_at)->toBeNull();

    $svc->transition($task, TaskStatus::Done);
    expect($task->fresh()->completed_at)->not->toBeNull();

    $svc->transition($task, TaskStatus::Doing); // reopen
    expect($task->fresh()->completed_at)->toBeNull()
        ->and(ActivityLog::query()->where('action', 'task.status_changed')->where('subject_id', $task->id)->count())->toBe(3);
});

// ---------------------------------------------------- member removal hook

it('unassigns tasks when their assignee is removed from the event', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $member = User::factory()->create();
    tk_join($event, $member);

    $mine = tk_task($event, $owner, ['assignee_id' => $member->id]);
    $ownersTask = tk_task($event, $owner, ['assignee_id' => $owner->id]);

    app(EventMemberService::class)->removeMember($event, $owner, $member);

    expect($mine->fresh()->assignee_id)->toBeNull()
        ->and($ownersTask->fresh()->assignee_id)->toBe($owner->id);
});

// --------------------------------------------------------------- progress

it('computes progress from leaf tasks only and ignores cancelled ones', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $committee = app(CommitteeService::class)->create($event, $owner, ['name' => 'اجرایی']);
    $svc = app(TaskService::class);

    // empty event/committee → 0 of 0, never a division by zero
    expect($svc->progressForEvent($event))->toBe(['total' => 0, 'done' => 0, 'percent' => 0])
        ->and($svc->progressForCommittee($committee))->toBe(['total' => 0, 'done' => 0, 'percent' => 0]);

    $a = tk_task($event, $owner, ['committee_id' => $committee->id]);
    tk_task($event, $owner, ['committee_id' => $committee->id]);                          // todo
    $cancelled = tk_task($event, $owner);
    $parent = tk_task($event, $owner);                                                   // has subtasks → not a leaf
    $s1 = tk_task($event, $owner, ['parent_id' => $parent->id]);
    tk_task($event, $owner, ['parent_id' => $parent->id]);                               // todo

    $svc->transition($a, TaskStatus::Done);
    $svc->transition($cancelled, TaskStatus::Cancelled);
    $svc->transition($s1, TaskStatus::Done);

    // leaves: a(done), b(todo), s1(done), s2(todo) → 2/4; cancelled and the parent are excluded
    expect($svc->progressForEvent($event))->toBe(['total' => 4, 'done' => 2, 'percent' => 50]);

    // committee: a(done), b(todo) → 1/2
    expect($svc->progressForCommittee($committee))->toBe(['total' => 2, 'done' => 1, 'percent' => 50]);
});

it('keeps task progress separate between events', function () {
    $owner = User::factory()->organizational()->create();
    $a = tk_event($owner);
    $b = tk_event($owner);
    $svc = app(TaskService::class);

    $done = tk_task($a, $owner);
    $svc->transition($done, TaskStatus::Done);
    tk_task($b, $owner);

    expect($svc->progressForEvent($a)['percent'])->toBe(100)
        ->and($svc->progressForEvent($b)['percent'])->toBe(0);
});
