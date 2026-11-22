<?php

use App\Enums\CommitteeRole;
use App\Enums\EventRole;
use App\Models\Task;
use App\Models\User;
use App\Services\CommitteeService;
use App\Services\EventService;
use App\Services\OrganizationService;
use App\Services\TaskService;

/**
 * Builds one event with every kind of actor:
 *  owner (creator) · manager (event_manager) · head (head of committee A)
 *  assignee (member, assigned taskA) · member (plain) · viewer · stranger (not in the event)
 */
function tk_policy_scene(): array
{
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $committees = app(CommitteeService::class);

    $comA = $committees->create($event, $owner, ['name' => 'الف']);
    $comB = $committees->create($event, $owner, ['name' => 'ب']);

    $manager = User::factory()->create();
    tk_join($event, $manager, EventRole::EventManager);

    $head = User::factory()->create();
    tk_join($event, $head);
    $committees->addMember($comA, $owner, $head, CommitteeRole::Head);

    $assignee = User::factory()->create();
    tk_join($event, $assignee);

    $member = User::factory()->create();
    tk_join($event, $member);

    $viewer = User::factory()->create();
    tk_join($event, $viewer, EventRole::Viewer);

    $stranger = User::factory()->organizational()->create();

    return [
        'event' => $event,
        'owner' => $owner,
        'manager' => $manager,
        'head' => $head,
        'assignee' => $assignee,
        'member' => $member,
        'viewer' => $viewer,
        'stranger' => $stranger,
        'comA' => $comA->fresh(),
        'comB' => $comB->fresh(),
        'taskA' => tk_task($event, $owner, ['committee_id' => $comA->id, 'assignee_id' => $assignee->id]),
        'taskB' => tk_task($event, $owner, ['committee_id' => $comB->id]),
        'loose' => tk_task($event, $owner),
    ];
}

it('applies the task permission matrix (view / update / delete / dependencies)', function () {
    ['event' => $event, 'owner' => $owner, 'manager' => $manager, 'head' => $head, 'assignee' => $assignee,
        'member' => $member, 'viewer' => $viewer, 'stranger' => $stranger, 'taskA' => $taskA, 'taskB' => $taskB] = tk_policy_scene();

    // view: everyone in the event, nobody outside
    foreach ([$owner, $manager, $head, $assignee, $member, $viewer] as $user) {
        expect($user->can('view', $taskA))->toBeTrue()
            ->and($user->can('viewAny', [Task::class, $event]))->toBeTrue();
    }
    expect($stranger->can('view', $taskA))->toBeFalse()
        ->and($stranger->can('viewAny', [Task::class, $event]))->toBeFalse();

    // update + manage dependencies: manager-level, or the head of THAT task's committee
    foreach (['update', 'manageDependencies'] as $ability) {
        expect($owner->can($ability, $taskA))->toBeTrue()
            ->and($manager->can($ability, $taskA))->toBeTrue()
            ->and($head->can($ability, $taskA))->toBeTrue()       // head of A, task in A
            ->and($head->can($ability, $taskB))->toBeFalse()      // task in B
            ->and($assignee->can($ability, $taskA))->toBeFalse()
            ->and($member->can($ability, $taskA))->toBeFalse()
            ->and($viewer->can($ability, $taskA))->toBeFalse()
            ->and($stranger->can($ability, $taskA))->toBeFalse();
    }

    // delete: manager-level only (a committee head cannot delete)
    expect($owner->can('delete', $taskA))->toBeTrue()
        ->and($manager->can('delete', $taskA))->toBeTrue()
        ->and($head->can('delete', $taskA))->toBeFalse()
        ->and($assignee->can('delete', $taskA))->toBeFalse()
        ->and($member->can('delete', $taskA))->toBeFalse()
        ->and($viewer->can('delete', $taskA))->toBeFalse()
        ->and($stranger->can('delete', $taskA))->toBeFalse();
});

it('applies the matrix for creating tasks', function () {
    ['event' => $event, 'owner' => $owner, 'manager' => $manager, 'head' => $head, 'assignee' => $assignee,
        'member' => $member, 'viewer' => $viewer, 'stranger' => $stranger, 'comA' => $comA, 'comB' => $comB] = tk_policy_scene();

    expect($owner->can('create', [Task::class, $event]))->toBeTrue()
        ->and($manager->can('create', [Task::class, $event]))->toBeTrue()
        ->and($head->can('create', [Task::class, $event]))->toBeFalse()          // no committee given
        ->and($head->can('create', [Task::class, $event, $comA]))->toBeTrue()    // own committee
        ->and($head->can('create', [Task::class, $event, $comB]))->toBeFalse()   // someone else's committee
        ->and($assignee->can('create', [Task::class, $event, $comA]))->toBeFalse()
        ->and($member->can('create', [Task::class, $event]))->toBeFalse()
        ->and($viewer->can('create', [Task::class, $event]))->toBeFalse()
        ->and($stranger->can('create', [Task::class, $event]))->toBeFalse();
});

it('applies the matrix for changing task status and attaching files', function () {
    ['owner' => $owner, 'manager' => $manager, 'head' => $head, 'assignee' => $assignee,
        'member' => $member, 'viewer' => $viewer, 'stranger' => $stranger, 'taskA' => $taskA, 'taskB' => $taskB] = tk_policy_scene();

    foreach (['transition', 'attach'] as $ability) {
        expect($owner->can($ability, $taskA))->toBeTrue()
            ->and($manager->can($ability, $taskA))->toBeTrue()
            ->and($head->can($ability, $taskA))->toBeTrue()           // own committee
            ->and($assignee->can($ability, $taskA))->toBeTrue()       // own task
            ->and($member->can($ability, $taskA))->toBeFalse()        // not theirs
            ->and($viewer->can($ability, $taskA))->toBeFalse()
            ->and($stranger->can($ability, $taskA))->toBeFalse()
            // a task of another committee: the head and the (non-)assignee have no rights
            ->and($head->can($ability, $taskB))->toBeFalse()
            ->and($assignee->can($ability, $taskB))->toBeFalse();
    }
});

it('never lets a viewer change a task even if it was assigned to them', function () {
    ['viewer' => $viewer, 'loose' => $loose] = tk_policy_scene();

    // the service refuses such an assignment; force it at DB level to prove the policy holds anyway
    Task::query()->whereKey($loose->id)->update(['assignee_id' => $viewer->id]);

    expect($viewer->can('transition', $loose->fresh()))->toBeFalse()
        ->and($viewer->can('attach', $loose->fresh()))->toBeFalse();
});

it('stops the assignee from changing a task after being removed from the event', function () {
    ['event' => $event, 'owner' => $owner, 'assignee' => $assignee, 'taskA' => $taskA] = tk_policy_scene();

    // simulate stale data: membership gone but assignee_id still set
    $event->members()->detach($assignee->id);

    expect($assignee->can('transition', $taskA->fresh()))->toBeFalse()
        ->and($assignee->can('view', $taskA->fresh()))->toBeFalse();
});

it('lets non-viewer members comment, but not viewers or strangers', function () {
    ['owner' => $owner, 'manager' => $manager, 'head' => $head, 'assignee' => $assignee,
        'member' => $member, 'viewer' => $viewer, 'stranger' => $stranger, 'taskA' => $taskA] = tk_policy_scene();

    foreach ([$owner, $manager, $head, $assignee, $member] as $user) {
        expect($user->can('comment', $taskA))->toBeTrue();
    }

    expect($viewer->can('comment', $taskA))->toBeFalse()
        ->and($stranger->can('comment', $taskA))->toBeFalse();
});

it('lets only the author or a manager delete a comment, and only through the right task', function () {
    ['owner' => $owner, 'manager' => $manager, 'assignee' => $assignee, 'member' => $member,
        'taskA' => $taskA, 'taskB' => $taskB] = tk_policy_scene();

    $comment = app(TaskService::class)->addComment($taskA, $member, 'سلام');

    expect($member->can('deleteComment', [$taskA, $comment]))->toBeTrue()     // author
        ->and($manager->can('deleteComment', [$taskA, $comment]))->toBeTrue()
        ->and($owner->can('deleteComment', [$taskA, $comment]))->toBeTrue()
        ->and($assignee->can('deleteComment', [$taskA, $comment]))->toBeFalse()
        // comment does not belong to taskB → denied for everyone, even the author and managers
        ->and($member->can('deleteComment', [$taskB, $comment]))->toBeFalse()
        ->and($manager->can('deleteComment', [$taskB, $comment]))->toBeFalse();
});

it('gives organization managers task rights on organization events, but not plain org members', function () {
    $owner = User::factory()->organizational()->create();
    $orgManager = User::factory()->organizational()->create();
    $orgMember = User::factory()->organizational()->create();

    $org = app(OrganizationService::class)->create($owner, ['name' => 'سازمان وظایف', 'type' => 'club']);
    $org->members()->attach($orgManager->id, ['role' => 'manager', 'joined_at' => now()]);
    $org->members()->attach($orgMember->id, ['role' => 'member', 'joined_at' => now()]);

    $event = app(EventService::class)->create($owner, ['title' => 'رویداد سازمانی', 'organization_id' => $org->id]);
    $task = tk_task($event, $owner);

    expect($orgManager->can('view', $task))->toBeTrue()
        ->and($orgManager->can('update', $task))->toBeTrue()
        ->and($orgManager->can('delete', $task))->toBeTrue()
        ->and($orgManager->can('transition', $task))->toBeTrue()
        ->and($orgManager->can('comment', $task))->toBeTrue()
        ->and($orgMember->can('view', $task))->toBeFalse()
        ->and($orgMember->can('update', $task))->toBeFalse();
});
