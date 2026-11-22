<?php

use App\Enums\TaskStatus;
use App\Models\User;
use App\Services\TaskService;

function tk_dep_scene(): array
{
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);

    return [$owner, $event, app(TaskService::class)];
}

// ------------------------------------------------------- automatic blocking

it('blocks a task while its prerequisite is unfinished and releases it when done', function () {
    [$owner, $event, $svc] = tk_dep_scene();
    $prereq = tk_task($event, $owner);
    $task = tk_task($event, $owner);

    $svc->addDependency($task, $prereq);
    expect($task->fresh()->status)->toBe(TaskStatus::Blocked);

    $svc->transition($prereq, TaskStatus::Done);
    expect($task->fresh()->status)->toBe(TaskStatus::Todo);
});

it('keeps a task blocked when its prerequisite is cancelled (only done releases)', function () {
    [$owner, $event, $svc] = tk_dep_scene();
    $prereq = tk_task($event, $owner);
    $task = tk_task($event, $owner);

    $svc->addDependency($task, $prereq);
    $svc->transition($prereq, TaskStatus::Cancelled);

    expect($task->fresh()->status)->toBe(TaskStatus::Blocked);
});

it('waits for ALL prerequisites', function () {
    [$owner, $event, $svc] = tk_dep_scene();
    $p1 = tk_task($event, $owner);
    $p2 = tk_task($event, $owner);
    $task = tk_task($event, $owner);

    $svc->addDependency($task, $p1);
    $svc->addDependency($task, $p2);

    $svc->transition($p1, TaskStatus::Done);
    expect($task->fresh()->status)->toBe(TaskStatus::Blocked);

    $svc->transition($p2, TaskStatus::Done);
    expect($task->fresh()->status)->toBe(TaskStatus::Todo);
});

it('re-blocks open dependents when a finished prerequisite is reopened', function () {
    [$owner, $event, $svc] = tk_dep_scene();
    $prereq = tk_task($event, $owner);
    $task = tk_task($event, $owner);

    $svc->addDependency($task, $prereq);
    $svc->transition($prereq, TaskStatus::Done);
    $svc->transition($task, TaskStatus::Doing);

    $svc->transition($prereq, TaskStatus::Doing); // reopened

    expect($task->fresh()->status)->toBe(TaskStatus::Blocked);
});

it('does not re-block a task that is already done', function () {
    [$owner, $event, $svc] = tk_dep_scene();
    $task = tk_task($event, $owner);
    $svc->transition($task, TaskStatus::Done);

    $late = tk_task($event, $owner);
    $svc->addDependency($task, $late);

    expect($task->fresh()->status)->toBe(TaskStatus::Done);
});

it('blocks a task that is already in progress when a new unfinished prerequisite is added', function () {
    [$owner, $event, $svc] = tk_dep_scene();
    $task = tk_task($event, $owner);
    $svc->transition($task, TaskStatus::Doing);

    $prereq = tk_task($event, $owner);
    $svc->addDependency($task, $prereq);

    expect($task->fresh()->status)->toBe(TaskStatus::Blocked);
});

it('unblocks a task when the dependency is removed', function () {
    [$owner, $event, $svc] = tk_dep_scene();
    $prereq = tk_task($event, $owner);
    $task = tk_task($event, $owner);

    $svc->addDependency($task, $prereq);
    $svc->removeDependency($task, $prereq);

    expect($task->fresh()->status)->toBe(TaskStatus::Todo)
        ->and($task->prerequisites()->count())->toBe(0);
});

it('unblocks dependents when their prerequisite is deleted', function () {
    [$owner, $event, $svc] = tk_dep_scene();
    $prereq = tk_task($event, $owner);
    $task = tk_task($event, $owner);

    $svc->addDependency($task, $prereq);
    $svc->delete($prereq);

    expect($task->fresh()->status)->toBe(TaskStatus::Todo);
});

// -------------------------------------------------------- manual transitions

it('forbids choosing the blocked status by hand', function () {
    [$owner, $event, $svc] = tk_dep_scene();
    $task = tk_task($event, $owner);

    $svc->transition($task, TaskStatus::Blocked);
})->throws(InvalidArgumentException::class);

it('forbids starting or finishing a blocked task, but allows cancelling it', function () {
    [$owner, $event, $svc] = tk_dep_scene();
    $prereq = tk_task($event, $owner);
    $task = tk_task($event, $owner);
    $svc->addDependency($task, $prereq);

    expect(fn () => $svc->transition($task, TaskStatus::Doing))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $svc->transition($task, TaskStatus::Done))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $svc->transition($task, TaskStatus::Todo))->toThrow(InvalidArgumentException::class);

    $svc->transition($task, TaskStatus::Cancelled);

    expect($task->fresh()->status)->toBe(TaskStatus::Cancelled);
});

// ------------------------------------------------------------------- cycles

it('rejects a task depending on itself', function () {
    [$owner, $event, $svc] = tk_dep_scene();
    $task = tk_task($event, $owner);

    $svc->addDependency($task, $task);
})->throws(InvalidArgumentException::class);

it('rejects a direct dependency cycle', function () {
    [$owner, $event, $svc] = tk_dep_scene();
    $a = tk_task($event, $owner);
    $b = tk_task($event, $owner);

    $svc->addDependency($a, $b);
    $svc->addDependency($b, $a);
})->throws(InvalidArgumentException::class);

it('rejects an indirect dependency cycle and leaves the graph untouched', function () {
    [$owner, $event, $svc] = tk_dep_scene();
    $a = tk_task($event, $owner);
    $b = tk_task($event, $owner);
    $c = tk_task($event, $owner);

    $svc->addDependency($a, $b); // a waits for b
    $svc->addDependency($b, $c); // b waits for c

    expect(fn () => $svc->addDependency($c, $a))->toThrow(InvalidArgumentException::class) // c waits for a → loop
        ->and($c->prerequisites()->count())->toBe(0)
        ->and($svc->wouldCreateCycle($c, $a))->toBeTrue()
        ->and($svc->wouldCreateCycle($a, $c))->toBeFalse(); // a waiting for c too is fine (diamond, not a loop)
});

it('ignores deleted tasks when checking for cycles', function () {
    [$owner, $event, $svc] = tk_dep_scene();
    $a = tk_task($event, $owner);
    $b = tk_task($event, $owner);
    $c = tk_task($event, $owner);

    $svc->addDependency($a, $b); // a waits for b
    $svc->addDependency($b, $c); // b waits for c

    expect($svc->wouldCreateCycle($c, $a))->toBeTrue();

    // b is deleted: a no longer (transitively) waits for c, so c may now wait for a
    $svc->delete($b);

    expect($svc->wouldCreateCycle($c, $a))->toBeFalse();

    $svc->addDependency($c, $a);

    expect($c->prerequisites()->count())->toBe(1);
});

it('rejects duplicate dependencies', function () {
    [$owner, $event, $svc] = tk_dep_scene();
    $a = tk_task($event, $owner);
    $b = tk_task($event, $owner);

    $svc->addDependency($a, $b);
    $svc->addDependency($a, $b);
})->throws(InvalidArgumentException::class);

it('rejects dependencies across events', function () {
    $owner = User::factory()->organizational()->create();
    $a = tk_task(tk_event($owner), $owner);
    $b = tk_task(tk_event($owner), $owner);

    app(TaskService::class)->addDependency($a, $b);
})->throws(InvalidArgumentException::class);
