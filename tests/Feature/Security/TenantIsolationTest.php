<?php

use App\Enums\EventRole;
use App\Livewire\Events\EventShow;
use App\Models\Event;
use App\Models\User;
use App\Services\EventService;
use App\Services\OrganizationService;
use Livewire\Livewire;

function iso_event(User $owner, array $data = []): Event
{
    return app(EventService::class)->create($owner, array_merge(['title' => 'رویداد خصوصی'], $data));
}

it('hides an event from users who are not part of it', function () {
    $owner = User::factory()->organizational()->create();
    $stranger = User::factory()->organizational()->create();
    $event = iso_event($owner);

    $this->actingAs($stranger)->get(route('events.show', $event))->assertForbidden();
    $this->actingAs($stranger)->get(route('events.edit', $event))->assertForbidden();
    $this->actingAs($stranger)->get(route('events.committees', $event))->assertForbidden();
});

it('does not list events of other users', function () {
    $a = User::factory()->organizational()->create();
    $b = User::factory()->organizational()->create();
    $eventA = iso_event($a);
    $eventB = iso_event($b);

    $idsForB = Event::query()->visibleTo($b)->pluck('id')->all();

    expect($idsForB)->toContain($eventB->id)->not->toContain($eventA->id);
});

it('hides organizations from non-members', function () {
    $owner = User::factory()->organizational()->create();
    $stranger = User::factory()->organizational()->create();
    $org = app(OrganizationService::class)->create($owner, ['name' => 'سازمان A', 'type' => 'club']);

    $this->actingAs($stranger)->get(route('orgs.show', $org))->assertForbidden();
    $this->actingAs($stranger)->get(route('orgs.edit', $org))->assertForbidden();
});

it('shows org events only to org managers, not plain org members', function () {
    $owner = User::factory()->organizational()->create();
    $plain = User::factory()->organizational()->create();
    $manager = User::factory()->organizational()->create();

    $org = app(OrganizationService::class)->create($owner, ['name' => 'سازمان B', 'type' => 'club']);
    $org->members()->attach($plain->id, ['role' => 'member', 'joined_at' => now()]);
    $org->members()->attach($manager->id, ['role' => 'manager', 'joined_at' => now()]);

    $event = iso_event($owner, ['organization_id' => $org->id]);

    expect(Event::query()->visibleTo($plain)->pluck('id')->all())->not->toContain($event->id)
        ->and(Event::query()->visibleTo($manager)->pluck('id')->all())->toContain($event->id);

    $this->actingAs($plain)->get(route('events.show', $event))->assertForbidden();
});

it('prevents a plain member from managing members through Livewire actions', function () {
    $owner = User::factory()->organizational()->create();
    $member = User::factory()->organizational()->create();
    $event = iso_event($owner);
    $event->members()->attach($member->id, [
        'role' => EventRole::Member->value,
        'added_by' => $owner->id,
        'joined_at' => now(),
    ]);

    Livewire::actingAs($member)
        ->test(EventShow::class, ['event' => $event])
        ->set('invitePhone', '09120001111')
        ->set('inviteRole', 'member')
        ->call('invite')
        ->assertForbidden();
});
