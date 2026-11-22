<?php

use App\Enums\EventRole;
use App\Livewire\Events\EventRisk;
use App\Livewire\Events\EventShow;
use App\Models\Event;
use App\Models\Incident;
use App\Models\Risk;
use App\Models\Task;
use App\Models\User;
use App\Services\OrganizationService;
use App\Support\Jalali;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

function rk_component(User $user, Event $event)
{
    return Livewire::actingAs($user)->test(EventRisk::class, ['event' => $event]);
}

/** Livewire may throw or answer 404 for a foreign id; both mean "blocked". */
function rk_blocked(callable $action): void
{
    try {
        $action();
    } catch (ModelNotFoundException|Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
        // expected
    }

    expect(true)->toBeTrue();
}

function rk_make_risk(Event $event, User $actor, array $data = []): Risk
{
    return app(App\Services\RiskService::class)->create($event, $actor, array_merge([
        'title' => 'ریسک آزمایشی',
        'category' => 'crowd',
        'likelihood' => 3,
        'impact' => 4,
    ], $data));
}

// ------------------------------------------------------------ policy matrix

it('applies the risk access matrix', function () {
    $owner = User::factory()->organizational()->create();
    $org = app(OrganizationService::class)->create($owner, ['name' => 'سازمان ریسک', 'type' => 'club']);
    $event = tk_event($owner);
    $event->update(['organization_id' => $org->id]);

    $manager = User::factory()->organizational()->create();
    $head = User::factory()->organizational()->create();
    $member = User::factory()->organizational()->create();
    $viewer = User::factory()->organizational()->create();
    $stranger = User::factory()->organizational()->create();

    tk_join($event, $manager, EventRole::EventManager);
    tk_join($event, $head, EventRole::CommitteeHead);
    tk_join($event, $member, EventRole::Member);
    tk_join($event, $viewer, EventRole::Viewer);

    $can = fn (User $u, string $ability): bool => $u->can($ability, [Risk::class, $event->fresh()]);

    // manage: manager-level only
    expect($can($owner, 'manage'))->toBeTrue()
        ->and($can($manager, 'manage'))->toBeTrue()
        ->and($can($head, 'manage'))->toBeFalse()
        ->and($can($member, 'manage'))->toBeFalse()
        ->and($can($viewer, 'manage'))->toBeFalse()
        ->and($can($stranger, 'manage'))->toBeFalse();

    // read the register: every event member, nobody else
    expect($can($manager, 'viewRegister'))->toBeTrue()
        ->and($can($head, 'viewRegister'))->toBeTrue()
        ->and($can($member, 'viewRegister'))->toBeTrue()
        ->and($can($viewer, 'viewRegister'))->toBeTrue()
        ->and($can($stranger, 'viewRegister'))->toBeFalse();

    // report an incident: managers, committee heads and members — not viewers/strangers
    expect($can($manager, 'reportIncident'))->toBeTrue()
        ->and($can($head, 'reportIncident'))->toBeTrue()
        ->and($can($member, 'reportIncident'))->toBeTrue()
        ->and($can($viewer, 'reportIncident'))->toBeFalse()
        ->and($can($stranger, 'reportIncident'))->toBeFalse();
});

// ---------------------------------------------------------------- component

it('forbids strangers from opening the risk tab', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $stranger = User::factory()->organizational()->create();

    Livewire::actingAs($stranger)->test(EventRisk::class, ['event' => $event])->assertForbidden();
});

it('lets a manager create, edit and delete a risk', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);

    $component = Livewire::actingAs($owner)->test(EventRisk::class, ['event' => $event])
        ->call('openRiskForm')
        ->set('rTitle', 'ازدحام در ورودی')
        ->set('rCategory', 'crowd')
        ->set('rLikelihood', '4')
        ->set('rImpact', '5')
        ->set('rResponse', 'reduce')
        ->set('rReview', Jalali::today())
        ->call('saveRisk')
        ->assertHasNoErrors();

    $risk = Risk::query()->where('event_id', $event->id)->firstOrFail();
    expect($risk->score)->toBe(20)
        ->and($risk->title)->toBe('ازدحام در ورودی')
        ->and($risk->review_date)->not->toBeNull();

    $component->call('editRisk', $risk->id)
        ->set('rImpact', '2')
        ->call('saveRisk')
        ->assertHasNoErrors();

    expect($risk->refresh()->score)->toBe(8);

    $component->call('deleteRisk', $risk->id);
    expect(Risk::query()->count())->toBe(0);
});

it('validates the risk form', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);

    Livewire::actingAs($owner)->test(EventRisk::class, ['event' => $event])
        ->call('openRiskForm')
        ->set('rTitle', '')
        ->set('rLikelihood', '9')
        ->set('rReview', 'not-a-date')
        ->call('saveRisk')
        ->assertHasErrors(['rTitle', 'rLikelihood']);

    expect(Risk::query()->count())->toBe(0);
});

it('does not let members or viewers manage risks', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $risk = rk_make_risk($event, $owner);

    $member = User::factory()->organizational()->create();
    $viewer = User::factory()->organizational()->create();
    tk_join($event, $member, EventRole::Member);
    tk_join($event, $viewer, EventRole::Viewer);

    foreach ([$member, $viewer] as $user) {
        rk_component($user, $event)->assertOk();

        rk_component($user, $event)->call('openRiskForm')->assertForbidden();
        rk_component($user, $event)->call('deleteRisk', $risk->id)->assertForbidden();
        rk_component($user, $event)->call('changeStatus', $risk->id, 'closed')->assertForbidden();
        rk_component($user, $event)->call('applyTemplate', 'medical')->assertForbidden();
    }

    expect($risk->refresh()->status->value)->toBe('open')
        ->and(Risk::query()->count())->toBe(1);
});

it('blocks a risk id from another event', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $other = tk_event($owner);
    $foreign = rk_make_risk($other, $owner);

    rk_blocked(fn () => rk_component($owner, $event)->call('editRisk', $foreign->id));
    rk_blocked(fn () => rk_component($owner, $event)->call('deleteRisk', $foreign->id));
    rk_blocked(fn () => rk_component($owner, $event)->call('changeStatus', $foreign->id, 'closed'));
    rk_blocked(fn () => rk_component($owner, $event)->call('toggleRisk', $foreign->id));

    expect($foreign->refresh()->status->value)->toBe('open')
        ->and(Risk::query()->whereKey($foreign->id)->exists())->toBeTrue();
});

it('creates and links control tasks from the component', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $other = tk_event($owner);
    $risk = rk_make_risk($event, $owner);
    $mine = tk_task($event, $owner);
    $foreign = tk_task($other, $owner);

    $component = Livewire::actingAs($owner)->test(EventRisk::class, ['event' => $event])
        ->call('toggleRisk', $risk->id)
        ->set('ctTitle', 'نصب موانع ورودی')
        ->call('createControlTask', $risk->id)
        ->assertHasNoErrors();

    expect(Task::query()->where('title', 'نصب موانع ورودی')->where('module', 'risk')->exists())->toBeTrue();

    $component->set('ctTaskId', (string) $mine->id)->call('linkTask', $risk->id);
    expect($risk->tasks()->whereKey($mine->id)->exists())->toBeTrue();

    $component->set('ctTaskId', (string) $foreign->id)->call('linkTask', $risk->id);
    expect($risk->tasks()->whereKey($foreign->id)->exists())->toBeFalse();
});

// ---------------------------------------------------------- plan restrictions

it('keeps individual accounts on the simple register', function () {
    $user = User::factory()->individual()->create();
    $event = tk_event($user);

    Livewire::actingAs($user)->test(EventRisk::class, ['event' => $event])
        ->call('setSection', 'matrix')->assertSet('section', 'register')
        ->call('setSection', 'incidents')->assertSet('section', 'register')
        ->call('setSection', 'templates')->assertSet('section', 'register')
        ->call('openRiskForm')
        ->set('rTitle', 'ریسک ساده')
        ->call('saveRisk')
        ->assertHasNoErrors();

    expect(Risk::query()->where('title', 'ریسک ساده')->exists())->toBeTrue();
});

// ----------------------------------------------------------------- incidents

it('lets a member report an incident but not resolve or delete it', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $member = User::factory()->organizational()->create();
    tk_join($event, $member, EventRole::Member);

    rk_component($member, $event)
        ->call('setSection', 'incidents')
        ->call('openIncidentForm')
        ->set('iTitle', 'قطع برق سالن')
        ->set('iSeverity', 'high')
        ->set('iDate', Jalali::today())
        ->set('iTime', '10:30')
        ->call('saveIncident')
        ->assertHasNoErrors();

    $incident = Incident::query()->where('event_id', $event->id)->firstOrFail();
    expect($incident->reported_by)->toBe($member->id)
        ->and($incident->severity->value)->toBe('high');

    rk_component($member, $event)->call('startResolve', $incident->id)->assertForbidden();
    rk_component($member, $event)->call('deleteIncident', $incident->id)->assertForbidden();
    rk_component($member, $event)->call('editIncident', $incident->id)->assertForbidden();

    // a manager can resolve it
    Livewire::actingAs($owner)->test(EventRisk::class, ['event' => $event])
        ->call('setSection', 'incidents')
        ->call('startResolve', $incident->id)
        ->set('resolutionText', 'برق پشتیبان وصل شد')
        ->call('saveResolution')
        ->assertHasNoErrors();

    expect($incident->refresh()->isResolved())->toBeTrue();
});

it('does not let a viewer report an incident', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $viewer = User::factory()->organizational()->create();
    tk_join($event, $viewer, EventRole::Viewer);

    Livewire::actingAs($viewer)->test(EventRisk::class, ['event' => $event])
        ->call('openIncidentForm')
        ->assertForbidden();

    expect(Incident::query()->count())->toBe(0);
});

it('rejects an invalid incident date or time', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);

    Livewire::actingAs($owner)->test(EventRisk::class, ['event' => $event])
        ->call('setSection', 'incidents')
        ->call('openIncidentForm')
        ->set('iTitle', 'حادثه')
        ->set('iDate', Jalali::today())
        ->set('iTime', '25:99')
        ->call('saveIncident')
        ->assertHasErrors(['iDate']);

    expect(Incident::query()->count())->toBe(0);
});

// ------------------------------------------------------------------- hub tab

it('shows the risk component on the event hub risk tab', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);

    Livewire::actingAs($owner)->test(EventShow::class, ['event' => $event])
        ->set('tab', 'risk')
        ->assertSeeLivewire(EventRisk::class);
});
