<?php

use App\Enums\EventRole;
use App\Livewire\App\Dashboard as AppDashboard;
use App\Livewire\Events\EventControlCenter;
use App\Livewire\Events\EventDashboard;
use App\Models\EventInvitation;
use App\Models\User;
use App\Services\CommitteeService;
use App\Services\DashboardService;
use Livewire\Livewire;

function db_scene(): array
{
    $owner = User::factory()->organizational()->create();

    return [$owner, tk_event($owner)];
}

it('renders the event dashboard for members and blocks outsiders', function () {
    $this->withoutVite();
    [$owner, $event] = db_scene();
    tk_task($event, $owner, ['title' => 'یک']);

    Livewire::actingAs($owner)->test(EventDashboard::class, ['event' => $event])->assertOk();
    Livewire::actingAs(User::factory()->create())->test(EventDashboard::class, ['event' => $event])->assertForbidden();

    $this->actingAs($owner)
        ->get(route('events.show', ['event' => $event, 'tab' => 'dashboard']))
        ->assertOk();
    $this->actingAs($owner)
        ->get(route('events.show', ['event' => $event, 'tab' => 'control']))
        ->assertOk();
});

it('reports task progress, overdue tasks and the committees that are behind', function () {
    [$owner, $event] = db_scene();
    $late = app(CommitteeService::class)->create($event, $owner, ['name' => 'عقب‌افتاده']);
    $fine = app(CommitteeService::class)->create($event, $owner, ['name' => 'منظم']);

    tk_task($event, $owner, ['committee_id' => $late->id, 'due_at' => now()->subDays(3)]);
    tk_task($event, $owner, ['committee_id' => $late->id, 'due_at' => now()->subDay()]);
    $done = tk_task($event, $owner, ['committee_id' => $fine->id]);
    app(\App\Services\TaskService::class)->transition($done, \App\Enums\TaskStatus::Done);

    $d = app(DashboardService::class)->forEvent($event, $owner);

    expect($d['overdue_total'])->toBe(2)
        ->and($d['progress']['total'])->toBe(3)
        ->and($d['progress']['done'])->toBe(1)
        ->and($d['committees_behind'])->toHaveCount(1)
        ->and($d['committees_behind'][0]['committee']->id)->toBe($late->id)
        ->and($d['committees_behind'][0]['overdue'])->toBe(2);
});

it('hides budget numbers from viewers but not from managers', function () {
    [$owner, $event] = db_scene();
    $viewer = User::factory()->create();
    tk_join($event, $viewer, EventRole::Viewer);
    $member = User::factory()->create();
    tk_join($event, $member);

    $service = app(DashboardService::class);

    expect($service->forEvent($event, $owner)['can_budget'])->toBeTrue()
        ->and($service->forEvent($event, $owner)['budget'])->not->toBeNull()
        ->and($service->forEvent($event, $member)['can_budget'])->toBeTrue()
        ->and($service->forEvent($event, $viewer)['can_budget'])->toBeFalse()
        ->and($service->forEvent($event, $viewer)['budget'])->toBeNull();
});

it('computes the countdown states', function () {
    [, $event] = db_scene();
    $service = app(DashboardService::class);
    $today = now('Asia/Tehran')->startOfDay();

    expect($service->countdown($event))->toBe(['days' => null, 'state' => 'none']);

    $event->update(['start_date' => $today->copy()->addDays(5)->toDateString()]);
    expect($service->countdown($event->fresh()))->toBe(['days' => 5, 'state' => 'upcoming']);

    $event->update(['start_date' => $today->toDateString(), 'end_date' => $today->copy()->addDays(2)->toDateString()]);
    expect($service->countdown($event->fresh())['state'])->toBe('today');

    $event->update(['start_date' => $today->copy()->subDay()->toDateString()]);
    expect($service->countdown($event->fresh())['state'])->toBe('running');

    $event->update(['start_date' => $today->copy()->subDays(9)->toDateString(), 'end_date' => $today->copy()->subDays(4)->toDateString()]);
    expect($service->countdown($event->fresh()))->toBe(['days' => 4, 'state' => 'finished']);
});

it('summarises the event-day control center from during-phase tasks only', function () {
    [$owner, $event] = db_scene();

    tk_task($event, $owner, ['phase' => 'during', 'title' => 'روز اجرا', 'due_at' => now()->subHour()]);
    tk_task($event, $owner, ['phase' => 'pre', 'title' => 'پیش از رویداد']);

    $c = app(DashboardService::class)->controlCenter($event);

    expect($c['task_total'])->toBe(1)
        ->and($c['overdue_during'])->toBe(1)
        ->and($c['open_tasks'])->toHaveCount(1);

    Livewire::actingAs($owner)->test(EventControlCenter::class, ['event' => $event])
        ->assertSee('روز اجرا')
        ->assertDontSee('پیش از رویداد');
});

it('lists pending invitations for the user\'s own phone and lets them accept from the dashboard', function () {
    [$owner, $event] = db_scene();
    $invitee = User::factory()->create();
    $stranger = User::factory()->create();

    $mine = EventInvitation::query()->create([
        'event_id' => $event->id,
        'phone' => $invitee->phone,
        'role' => 'member',
        'token_hash' => EventInvitation::hashToken('tok-mine'),
        'invited_by' => $owner->id,
        'expires_at' => now()->addDays(3),
        'status' => 'pending',
    ]);

    $service = app(DashboardService::class);
    expect($service->forUser($invitee)['invitations'])->toHaveCount(1)
        ->and($service->forUser($stranger)['invitations'])->toHaveCount(0);

    // someone else's invitation id is treated as invalid
    Livewire::actingAs($stranger)->test(AppDashboard::class)->call('acceptInvitation', $mine->id);
    expect($event->hasMember($stranger))->toBeFalse();

    Livewire::actingAs($invitee)->test(AppDashboard::class)->call('acceptInvitation', $mine->id);
    expect($event->hasMember($invitee))->toBeTrue()
        ->and($mine->fresh()->status)->toBe('accepted');
});

it('counts the user\'s open and overdue tasks for the home dashboard', function () {
    [$owner, $event] = db_scene();
    $member = User::factory()->create();
    tk_join($event, $member);

    tk_task($event, $owner, ['assignee_id' => $member->id, 'due_at' => now()->subDay()]);
    tk_task($event, $owner, ['assignee_id' => $member->id, 'due_at' => now()->addDays(4)]);

    $mine = app(DashboardService::class)->forUser($member);

    expect($mine['open_tasks'])->toBe(2)
        ->and($mine['overdue_tasks'])->toBe(1);

    Livewire::actingAs($member)->test(AppDashboard::class)->assertOk();
});
