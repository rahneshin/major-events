<?php

use App\Enums\EventRole;
use App\Enums\TaskStatus;
use App\Livewire\Tasks\MyTasks;
use App\Models\User;
use App\Services\TaskService;
use Livewire\Livewire;

it('shows only my tasks across events, grouped by event', function () {
    $owner = User::factory()->organizational()->create();
    $me = User::factory()->create();
    $a = tk_event($owner);
    $b = tk_event($owner);
    tk_join($a, $me);
    tk_join($b, $me);

    tk_task($a, $owner, ['title' => 'وظیفه من در الف', 'assignee_id' => $me->id]);
    tk_task($b, $owner, ['title' => 'وظیفه من در ب', 'assignee_id' => $me->id]);
    tk_task($a, $owner, ['title' => 'وظیفه دیگران']);

    Livewire::actingAs($me)->test(MyTasks::class)
        ->assertOk()
        ->assertSee('وظیفه من در الف')
        ->assertSee('وظیفه من در ب')
        ->assertDontSee('وظیفه دیگران');
});

it('does not list tasks of events I can no longer see', function () {
    $owner = User::factory()->organizational()->create();
    $me = User::factory()->create();
    $event = tk_event($owner);
    tk_join($event, $me);
    tk_task($event, $owner, ['title' => 'وظیفه گذشته', 'assignee_id' => $me->id]);

    $event->members()->detach($me->id);

    Livewire::actingAs($me)->test(MyTasks::class)->assertDontSee('وظیفه گذشته');
});

it('filters open, overdue and done tasks', function () {
    $owner = User::factory()->organizational()->create();
    $me = User::factory()->create();
    $event = tk_event($owner);
    tk_join($event, $me);
    $svc = app(TaskService::class);

    tk_task($event, $owner, ['title' => 'باز عادی', 'assignee_id' => $me->id]);
    tk_task($event, $owner, ['title' => 'باز دیرکرده', 'assignee_id' => $me->id, 'due_at' => now()->subDay()]);
    $done = tk_task($event, $owner, ['title' => 'انجام شده', 'assignee_id' => $me->id]);
    $svc->transition($done, TaskStatus::Done);

    Livewire::actingAs($me)->test(MyTasks::class)
        ->assertSee('باز عادی')->assertSee('باز دیرکرده')->assertDontSee('انجام شده')
        ->call('setFilter', 'overdue')->assertSee('باز دیرکرده')->assertDontSee('باز عادی')
        ->call('setFilter', 'done')->assertSee('انجام شده')->assertDontSee('باز عادی')
        ->call('setFilter', 'all')->assertSee('باز عادی')->assertSee('انجام شده');
});

it('lets me change the status of my own task', function () {
    $owner = User::factory()->organizational()->create();
    $me = User::factory()->create();
    $event = tk_event($owner);
    tk_join($event, $me);
    $task = tk_task($event, $owner, ['assignee_id' => $me->id]);

    Livewire::actingAs($me)->test(MyTasks::class)->call('changeStatus', $task->id, 'doing');

    expect($task->fresh()->status)->toBe(TaskStatus::Doing);
});

it('refuses to touch a task that is not mine', function () {
    $owner = User::factory()->organizational()->create();
    $me = User::factory()->create();
    $event = tk_event($owner);
    tk_join($event, $me);
    $task = tk_task($event, $owner); // unassigned

    tk_blocked(fn () => Livewire::actingAs($me)->test(MyTasks::class)->call('changeStatus', $task->id, 'done'));

    expect($task->fresh()->status)->toBe(TaskStatus::Todo);
});

it('blocks status changes for a viewer who still has an assigned task', function () {
    $owner = User::factory()->organizational()->create();
    $viewer = User::factory()->create();
    $event = tk_event($owner);
    tk_join($event, $viewer, EventRole::Viewer);
    $task = tk_task($event, $owner);
    \App\Models\Task::query()->whereKey($task->id)->update(['assignee_id' => $viewer->id]);

    Livewire::actingAs($viewer)->test(MyTasks::class)->call('changeStatus', $task->id, 'done')->assertForbidden();

    expect($task->fresh()->status)->toBe(TaskStatus::Todo);
});

it('reports the blocked-by-prerequisite rule instead of crashing', function () {
    $owner = User::factory()->organizational()->create();
    $me = User::factory()->create();
    $event = tk_event($owner);
    tk_join($event, $me);
    $svc = app(TaskService::class);

    $prereq = tk_task($event, $owner);
    $task = tk_task($event, $owner, ['assignee_id' => $me->id]);
    $svc->addDependency($task, $prereq);

    Livewire::actingAs($me)->test(MyTasks::class)
        ->call('changeStatus', $task->id, 'doing')
        ->assertSet('error', __('task.blocked_by_prerequisites'));
});
