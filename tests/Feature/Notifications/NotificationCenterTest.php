<?php

use App\Livewire\Notifications\NotificationBell;
use App\Livewire\Notifications\NotificationCenter;
use App\Models\User;
use App\Notifications\AddedToCommittee;
use App\Services\CommitteeService;
use App\Services\NotificationPreferenceService;
use Livewire\Livewire;

function nc_notify(User $user): void
{
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $committee = app(CommitteeService::class)->create($event, $owner, ['name' => 'فنی']);

    $user->notify(new AddedToCommittee($committee, 'member'));
}

it('requires login for the notification page', function () {
    $this->get(route('notifications.index'))->assertRedirect(route('auth.login'));
});

it('shows the unread counter and marks notifications read', function () {
    $user = User::factory()->create();
    nc_notify($user);
    nc_notify($user);

    $component = Livewire::actingAs($user)->test(NotificationBell::class)
        ->assertViewHas('unread', 2);

    $component->call('markRead', $user->notifications()->first()->id)
        ->assertViewHas('unread', 1);

    $component->call('markAllRead')->assertViewHas('unread', 0);
});

it('never lets a user touch another user\'s notifications', function () {
    $victim = User::factory()->create();
    $attacker = User::factory()->create();
    nc_notify($victim);

    Livewire::actingAs($attacker)->test(NotificationCenter::class)
        ->call('markRead', $victim->notifications()->first()->id)
        ->call('markAllRead');

    expect($victim->unreadNotifications()->count())->toBe(1);
});

it('toggles channel preferences from the center', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(NotificationCenter::class)
        ->call('toggle', 'task_due', 'sms')
        ->call('toggle', 'committee', 'sms') // unsupported: ignored
        ->call('toggle', 'nope', 'in_app');  // unknown: ignored

    $prefs = app(NotificationPreferenceService::class)->forUser($user);

    expect($prefs['task_due']['sms'])->toBeFalse()
        ->and($prefs['committee']['sms'])->toBeFalse()
        ->and($prefs['task_due']['in_app'])->toBeTrue();
});
