<?php

use App\Enums\TaskStatus;
use App\Models\User;
use App\Services\TaskService;
use Illuminate\Support\Facades\Artisan;

function tkr_member(User $owner, $event): User
{
    $member = User::factory()->create();
    tk_join($event, $member);

    return $member;
}

it('reminds the assignee of a task due soon, once per due date', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $member = tkr_member($owner, $event);

    tk_task($event, $owner, ['title' => 'نزدیک موعد', 'assignee_id' => $member->id, 'due_at' => now()->addHours(10)]);

    Artisan::call('tasks:remind');
    Artisan::call('tasks:remind'); // same day: no duplicate

    expect($member->notifications()->count())->toBe(1)
        ->and($member->notifications()->first()->data['type'])->toBe('task.due_soon')
        ->and($member->notifications()->first()->data['task_title'])->toBe('نزدیک موعد');
});

it('reminds about overdue tasks', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $member = tkr_member($owner, $event);

    tk_task($event, $owner, ['assignee_id' => $member->id, 'due_at' => now()->subDays(2)]);

    Artisan::call('tasks:remind');

    expect($member->notifications()->first()->data['type'])->toBe('task.overdue');
});

it('skips tasks that are far in the future, undated, unassigned, done, cancelled or blocked', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $member = tkr_member($owner, $event);
    $svc = app(TaskService::class);

    tk_task($event, $owner, ['assignee_id' => $member->id, 'due_at' => now()->addDays(10)]);   // far
    tk_task($event, $owner, ['assignee_id' => $member->id]);                                   // undated
    tk_task($event, $owner, ['due_at' => now()->addHours(5)]);                                 // unassigned

    $done = tk_task($event, $owner, ['assignee_id' => $member->id, 'due_at' => now()->addHours(5)]);
    $svc->transition($done, TaskStatus::Done);

    $cancelled = tk_task($event, $owner, ['assignee_id' => $member->id, 'due_at' => now()->addHours(5)]);
    $svc->transition($cancelled, TaskStatus::Cancelled);

    $prereq = tk_task($event, $owner);
    $blocked = tk_task($event, $owner, ['assignee_id' => $member->id, 'due_at' => now()->addHours(5)]);
    $svc->addDependency($blocked, $prereq);

    Artisan::call('tasks:remind');

    expect($member->notifications()->count())->toBe(0);
});

it('honours the --days window', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $member = tkr_member($owner, $event);

    tk_task($event, $owner, ['assignee_id' => $member->id, 'due_at' => now()->addDays(3)]);

    Artisan::call('tasks:remind');
    expect($member->notifications()->count())->toBe(0);

    Artisan::call('tasks:remind', ['--days' => 5]);
    expect($member->notifications()->count())->toBe(1);
});

it('does not remind for archived or cancelled events', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $member = tkr_member($owner, $event);
    tk_task($event, $owner, ['assignee_id' => $member->id, 'due_at' => now()->addHours(5)]);

    $event->forceFill(['status' => \App\Enums\EventStatus::Cancelled])->save();

    Artisan::call('tasks:remind');

    expect($member->notifications()->count())->toBe(0);
});
