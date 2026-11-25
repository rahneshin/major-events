<?php

require_once __DIR__.'/../../Support/CompetitionTestHelpers.php';

use App\Enums\CompetitionRole;
use App\Enums\EventRole;
use App\Livewire\Competitions\CompetitionShow;
use App\Livewire\Events\EventCompetitions;
use App\Models\Competition;
use App\Models\User;
use App\Services\CompetitionService;
use Livewire\Livewire;

function ca_setup(): array
{
    $owner = cp_owner();
    $event = tk_event($owner);
    $competition = cp_competition($event, $owner);

    return [$owner, $event, $competition];
}

it('gives the event owner full control', function () {
    [$owner, $event, $competition] = ca_setup();

    expect($owner->can('viewAny', [Competition::class, $event]))->toBeTrue()
        ->and($owner->can('create', [Competition::class, $event]))->toBeTrue()
        ->and($owner->can('manage', $competition))->toBeTrue()
        ->and($owner->can('enterResult', $competition))->toBeTrue()
        ->and($owner->can('confirmResult', $competition))->toBeTrue()
        ->and($owner->can('assignRoles', $competition))->toBeTrue()
        ->and($owner->can('delete', $competition))->toBeTrue();
});

it('lets a scorer enter results but not manage or confirm', function () {
    [$owner, $event, $competition] = ca_setup();
    $scorer = User::factory()->create();
    tk_join($event, $scorer);
    app(CompetitionService::class)->assignRole($competition, $owner, $scorer, CompetitionRole::Scorer);

    expect($scorer->can('view', $competition))->toBeTrue()
        ->and($scorer->can('enterResult', $competition))->toBeTrue()
        ->and($scorer->can('manage', $competition))->toBeFalse()
        ->and($scorer->can('confirmResult', $competition))->toBeFalse()
        ->and($scorer->can('assignRoles', $competition))->toBeFalse()
        ->and($scorer->can('delete', $competition))->toBeFalse();
});

it('lets a competition manager manage only that competition', function () {
    [$owner, $event, $competition] = ca_setup();
    $other = cp_competition($event, $owner, 'school_league', 'بسکتبال');
    $manager = User::factory()->create();
    tk_join($event, $manager);
    app(CompetitionService::class)->assignRole($competition, $owner, $manager, CompetitionRole::Manager);

    expect($manager->can('manage', $competition))->toBeTrue()
        ->and($manager->can('confirmResult', $competition))->toBeTrue()
        ->and($manager->can('assignRoles', $competition))->toBeFalse()
        ->and($manager->can('create', [Competition::class, $event]))->toBeFalse()
        ->and($manager->can('manage', $other))->toBeFalse()
        ->and($manager->can('enterResult', $other))->toBeFalse();
});

it('gives plain members and viewers read-only access', function () {
    [$owner, $event, $competition] = ca_setup();
    $member = User::factory()->create();
    $viewer = User::factory()->create();
    tk_join($event, $member);
    tk_join($event, $viewer, EventRole::Viewer);

    foreach ([$member, $viewer] as $user) {
        expect($user->can('view', $competition))->toBeTrue()
            ->and($user->can('manage', $competition))->toBeFalse()
            ->and($user->can('enterResult', $competition))->toBeFalse();
    }
});

it('denies outsiders everything', function () {
    [$owner, $event, $competition] = ca_setup();
    $outsider = cp_owner();

    expect($outsider->can('viewAny', [Competition::class, $event]))->toBeFalse()
        ->and($outsider->can('view', $competition))->toBeFalse()
        ->and($outsider->can('manage', $competition))->toBeFalse()
        ->and($outsider->can('enterResult', $competition))->toBeFalse();
});

it('drops competition roles when the user leaves the event', function () {
    [$owner, $event, $competition] = ca_setup();
    $manager = User::factory()->create();
    tk_join($event, $manager);
    app(CompetitionService::class)->assignRole($competition, $owner, $manager, CompetitionRole::Manager);

    $event->members()->detach($manager->id);

    expect($manager->can('manage', $competition))->toBeFalse()
        ->and($manager->can('view', $competition))->toBeFalse();
});

it('shows the competition page to members and forbids outsiders', function () {
    [$owner, $event, $competition] = ca_setup();
    $outsider = cp_owner();

    $this->actingAs($owner)
        ->get(route('competitions.show', ['event' => $event, 'competition' => $competition]))
        ->assertOk()
        ->assertSee($competition->name);

    $this->actingAs($outsider)
        ->get(route('competitions.show', ['event' => $event, 'competition' => $competition]))
        ->assertForbidden();
});

it('returns 404 when the competition belongs to another event', function () {
    [$owner, $event, $competition] = ca_setup();
    $otherEvent = tk_event($owner);

    $this->actingAs($owner)
        ->get(route('competitions.show', ['event' => $otherEvent, 'competition' => $competition]))
        ->assertNotFound();
});

it('lets a manager register through the component but not a scorer', function () {
    [$owner, $event, $competition] = ca_setup();
    $scorer = User::factory()->create();
    tk_join($event, $scorer);
    app(CompetitionService::class)->assignRole($competition, $owner, $scorer, CompetitionRole::Scorer);

    Livewire::actingAs($owner)
        ->test(CompetitionShow::class, ['event' => $event, 'competition' => $competition])
        ->set('rName', 'تیم الف')
        ->call('register')
        ->assertHasNoErrors();

    expect($competition->competitors()->count())->toBe(1);

    Livewire::actingAs($scorer)
        ->test(CompetitionShow::class, ['event' => $event, 'competition' => $competition])
        ->set('rName', 'تیم ب')
        ->call('register')
        ->assertForbidden();

    expect($competition->competitors()->count())->toBe(1);
});

it('keeps component actions inside the current competition', function () {
    [$owner, $event, $competition] = ca_setup();
    $other = cp_competition($event, $owner, 'school_league', 'دیگر');
    cp_fill($other, $owner, 2);
    $foreign = $other->competitors()->first();

    tk_blocked(function () use ($owner, $event, $competition, $foreign) {
        Livewire::actingAs($owner)
            ->test(CompetitionShow::class, ['event' => $event, 'competition' => $competition])
            ->call('withdrawCompetitor', $foreign->id);
    });

    expect($foreign->refresh()->status->value)->toBe('confirmed');
});

it('runs registration, draw and fixtures end to end through the component', function () {
    [$owner, $event, $competition] = ca_setup();

    $component = Livewire::actingAs($owner)
        ->test(CompetitionShow::class, ['event' => $event, 'competition' => $competition])
        ->set('bulkText', "الف، مدرسه ۱\nب، مدرسه ۲\nج، مدرسه ۳")
        ->call('bulkRegister')
        ->assertHasNoErrors();

    foreach ($competition->competitors()->get() as $competitor) {
        $component->call('confirmCompetitor', $competitor->id);
    }

    $component
        ->call('setSection', 'draw')
        ->set('dMethod', 'random')
        ->call('previewDraw')
        ->assertSet('error', null)
        ->call('confirmDraw')
        ->assertSet('error', null)
        ->call('generateFixtures')
        ->assertSet('error', null)
        ->call('markReady')
        ->assertSet('error', null);

    expect($competition->refresh()->status->value)->toBe('ready')
        ->and($competition->firstStage()->matches()->count())->toBe(3);
});

it('creates a competition from the event tab component', function () {
    $owner = cp_owner();
    $event = tk_event($owner);

    Livewire::actingAs($owner)
        ->test(EventCompetitions::class, ['event' => $event])
        ->call('openForm')
        ->set('cTemplate', 'school_league')
        ->set('cName', 'والیبال')
        ->set('cGender', 'women')
        ->call('save')
        ->assertHasNoErrors();

    expect(Competition::query()->forEvent($event)->where('name', 'والیبال')->first()?->gender->value)->toBe('women');
});

it('forbids creating a competition from the tab for non-managers', function () {
    $owner = cp_owner();
    $event = tk_event($owner);
    $member = User::factory()->create();
    tk_join($event, $member);

    Livewire::actingAs($member)
        ->test(EventCompetitions::class, ['event' => $event])
        ->call('openForm')
        ->assertForbidden();
});

it('forbids the tab for outsiders', function () {
    $owner = cp_owner();
    $event = tk_event($owner);

    Livewire::actingAs(cp_owner())
        ->test(EventCompetitions::class, ['event' => $event])
        ->assertForbidden();
});

it('shows the upsell instead of the module when the creator is on an individual plan', function () {
    $owner = User::factory()->individual()->create();
    $event = tk_event($owner);

    Livewire::actingAs($owner)
        ->test(EventCompetitions::class, ['event' => $event])
        ->assertSee(__('competition.feature_unavailable_title'));
});
