<?php

use App\Enums\CommitteeRole;
use App\Enums\CommitteeType;
use App\Enums\EventRole;
use App\Enums\EventType;
use App\Exceptions\LimitExceededException;
use App\Livewire\Events\EventCommittees;
use App\Livewire\Events\EventShow;
use App\Models\Committee;
use App\Models\Event;
use App\Models\User;
use App\Notifications\AddedToCommittee;
use App\Services\CommitteeService;
use App\Services\EventMemberService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

function makeEvent(User $owner, EventType $type = EventType::Local): Event
{
    return Event::query()->create([
        'created_by' => $owner->id,
        'title' => 'رویداد '.fake()->unique()->numerify('####'),
        'event_type' => $type,
        'scale' => 'small',
        'status' => 'draft',
    ]);
}

function joinEvent(Event $event, User $user, EventRole $role = EventRole::Member): void
{
    $event->members()->attach($user->id, [
        'role' => $role->value,
        'added_by' => $event->created_by,
        'joined_at' => now(),
    ]);
}

// ---------------------------------------------------------------- limits

it('enforces committees_per_event for individual accounts', function () {
    $owner = User::factory()->individual()->create();
    $event = makeEvent($owner);
    $svc = app(CommitteeService::class);

    foreach (['الف', 'ب', 'ج'] as $name) {
        $svc->create($event, $owner, ['name' => $name]);
    }

    expect($event->committees()->count())->toBe(3);

    $svc->create($event, $owner, ['name' => 'د']);
})->throws(LimitExceededException::class);

it('does not limit committees for organizational accounts', function () {
    $owner = User::factory()->organizational()->create();
    $event = makeEvent($owner);
    $svc = app(CommitteeService::class);

    foreach (range(1, 6) as $i) {
        $svc->create($event, $owner, ['name' => "کمیته $i"]);
    }

    expect($event->committees()->count())->toBe(6);
});

// -------------------------------------------------------------- templates

it('builds the suggested template by event type', function () {
    $owner = User::factory()->organizational()->create();
    $event = makeEvent($owner, EventType::Mega);

    $created = app(CommitteeService::class)->applyTemplate($event, $owner);

    expect($created)->toHaveCount(count(CommitteeType::templateFor(EventType::Mega)))
        ->and($event->committees()->pluck('position')->all())->toBe(range(1, $created->count()));
});

it('stops the template at the plan limit and skips existing types', function () {
    $owner = User::factory()->individual()->create();
    $event = makeEvent($owner, EventType::Local); // 4 suggested types, limit 3
    $svc = app(CommitteeService::class);

    $svc->create($event, $owner, ['name' => 'مالی من', 'type' => CommitteeType::Finance]);
    $created = $svc->applyTemplate($event, $owner);

    expect($created)->toHaveCount(2) // 1 existing + 2 new = limit 3
        ->and($event->committees()->where('type', 'finance')->count())->toBe(1);
});

it('throws when the template is applied with no free slot', function () {
    $owner = User::factory()->individual()->create();
    $event = makeEvent($owner);
    $svc = app(CommitteeService::class);

    foreach ([CommitteeType::Custom, CommitteeType::Custom, CommitteeType::Custom] as $i => $t) {
        $svc->create($event, $owner, ['name' => "c$i", 'type' => $t]);
    }

    $svc->applyTemplate($event, $owner);
})->throws(LimitExceededException::class);

// ---------------------------------------------------------------- members

it('requires committee members to be event members', function () {
    $owner = User::factory()->organizational()->create();
    $outsider = User::factory()->create();
    $event = makeEvent($owner);
    $svc = app(CommitteeService::class);
    $committee = $svc->create($event, $owner, ['name' => 'فنی']);

    $svc->addMember($committee, $owner, $outsider);
})->throws(InvalidArgumentException::class);

it('can add a non-member to the event and committee at once', function () {
    $owner = User::factory()->organizational()->create();
    $newcomer = User::factory()->create();
    $event = makeEvent($owner);
    $svc = app(CommitteeService::class);
    $committee = $svc->create($event, $owner, ['name' => 'فنی']);

    $svc->addMember($committee, $owner, $newcomer, addToEvent: true);

    expect($event->hasMember($newcomer))->toBeTrue()
        ->and($committee->hasMember($newcomer))->toBeTrue();
});

it('rejects duplicate committee members', function () {
    $owner = User::factory()->organizational()->create();
    $member = User::factory()->create();
    $event = makeEvent($owner);
    joinEvent($event, $member);
    $svc = app(CommitteeService::class);
    $committee = $svc->create($event, $owner, ['name' => 'فنی']);

    $svc->addMember($committee, $owner, $member);
    $svc->addMember($committee, $owner, $member);
})->throws(InvalidArgumentException::class);

it('notifies the added member in-app but not the actor', function () {
    Notification::fake();

    $owner = User::factory()->organizational()->create();
    $member = User::factory()->create();
    $event = makeEvent($owner);
    joinEvent($event, $member);
    $svc = app(CommitteeService::class);
    $committee = $svc->create($event, $owner, ['name' => 'فنی']);

    $svc->addMember($committee, $owner, $member);
    $svc->addMember($committee, $owner, $owner);

    Notification::assertSentTo($member, AddedToCommittee::class);
    Notification::assertNotSentTo($owner, AddedToCommittee::class);
});

// ------------------------------------------------------------------- head

it('promotes the head to committee_head and demotes the previous head', function () {
    $owner = User::factory()->organizational()->create();
    $a = User::factory()->create();
    $b = User::factory()->create();
    $event = makeEvent($owner);
    joinEvent($event, $a);
    joinEvent($event, $b);
    $svc = app(CommitteeService::class);
    $committee = $svc->create($event, $owner, ['name' => 'مالی']);
    $svc->addMember($committee, $owner, $a);
    $svc->addMember($committee, $owner, $b);

    $svc->setHead($committee, $a);
    expect($event->roleOf($a))->toBe(EventRole::CommitteeHead)
        ->and($committee->fresh()->head_user_id)->toBe($a->id)
        ->and($committee->roleOf($a))->toBe(CommitteeRole::Head);

    $svc->setHead($committee->fresh(), $b);
    expect($event->roleOf($a))->toBe(EventRole::Member)
        ->and($event->roleOf($b))->toBe(EventRole::CommitteeHead)
        ->and($committee->roleOf($a))->toBe(CommitteeRole::Member);
});

it('requires the head to be a committee member', function () {
    $owner = User::factory()->organizational()->create();
    $a = User::factory()->create();
    $event = makeEvent($owner);
    joinEvent($event, $a);
    $svc = app(CommitteeService::class);
    $committee = $svc->create($event, $owner, ['name' => 'مالی']);

    $svc->setHead($committee, $a);
})->throws(InvalidArgumentException::class);

it('never demotes an event manager when they stop heading a committee', function () {
    $owner = User::factory()->organizational()->create();
    $manager = User::factory()->create();
    $event = makeEvent($owner);
    joinEvent($event, $manager, EventRole::EventManager);
    $svc = app(CommitteeService::class);
    $committee = $svc->create($event, $owner, ['name' => 'مالی']);
    $svc->addMember($committee, $owner, $manager);
    $svc->setHead($committee, $manager);

    $svc->removeMember($committee->fresh(), $manager);

    expect($event->roleOf($manager))->toBe(EventRole::EventManager)
        ->and($committee->fresh()->head_user_id)->toBeNull();
});

it('removes a user from committees when removed from the event', function () {
    $owner = User::factory()->organizational()->create();
    $member = User::factory()->create();
    $event = makeEvent($owner);
    joinEvent($event, $member);
    $svc = app(CommitteeService::class);
    $committee = $svc->create($event, $owner, ['name' => 'مالی']);
    $svc->addMember($committee, $owner, $member);
    $svc->setHead($committee, $member);

    app(EventMemberService::class)->removeMember($event, $owner, $member);

    expect($committee->members()->count())->toBe(0)
        ->and($committee->fresh()->head_user_id)->toBeNull();
});

// ------------------------------------------------------ order / delete

it('reorders committees and keeps positions contiguous after delete', function () {
    $owner = User::factory()->organizational()->create();
    $event = makeEvent($owner);
    $svc = app(CommitteeService::class);
    $a = $svc->create($event, $owner, ['name' => 'الف']);
    $b = $svc->create($event, $owner, ['name' => 'ب']);
    $c = $svc->create($event, $owner, ['name' => 'ج']);

    $svc->move($c, 'up');
    expect($event->committees()->pluck('name')->all())->toBe(['الف', 'ج', 'ب']);

    $svc->delete($a->fresh());
    expect($event->committees()->pluck('position')->all())->toBe([1, 2]);
});

it('blocks deleting a committee that still has tasks', function () {
    // The tasks table arrives in Step 08; simulate its minimal shape here.
    Schema::create('tasks', function ($table) {
        $table->id();
        $table->unsignedBigInteger('committee_id')->nullable();
        $table->softDeletes();
    });

    $owner = User::factory()->organizational()->create();
    $event = makeEvent($owner);
    $svc = app(CommitteeService::class);
    $committee = $svc->create($event, $owner, ['name' => 'فنی']);
    DB::table('tasks')->insert(['committee_id' => $committee->id]);

    expect(fn () => $svc->delete($committee))->toThrow(InvalidArgumentException::class)
        ->and(Committee::query()->whereKey($committee->id)->exists())->toBeTrue();

    DB::table('tasks')->truncate();
    $svc->delete($committee);
    expect(Committee::query()->whereKey($committee->id)->exists())->toBeFalse();

    Schema::drop('tasks');
});

// --------------------------------------------------------------- policy

it('applies the committee permission matrix', function () {
    $owner = User::factory()->organizational()->create();
    $manager = User::factory()->create();
    $head = User::factory()->create();
    $member = User::factory()->create();
    $viewer = User::factory()->create();
    $stranger = User::factory()->create();

    $event = makeEvent($owner);
    joinEvent($event, $manager, EventRole::EventManager);
    joinEvent($event, $head);
    joinEvent($event, $member);
    joinEvent($event, $viewer, EventRole::Viewer);

    $svc = app(CommitteeService::class);
    $committee = $svc->create($event, $owner, ['name' => 'فنی']);
    $svc->addMember($committee, $owner, $head);
    $svc->setHead($committee, $head);
    $committee = $committee->fresh();

    // [user, view, create, update, delete, manageMembers, updateDescription]
    $matrix = [
        'owner' => [$owner, true, true, true, true, true, true],
        'manager' => [$manager, true, true, true, true, true, true],
        'head' => [$head, true, false, false, false, true, true],
        'member' => [$member, true, false, false, false, false, false],
        'viewer' => [$viewer, true, false, false, false, false, false],
        'stranger' => [$stranger, false, false, false, false, false, false],
    ];

    foreach ($matrix as $who => [$user, $view, $create, $update, $delete, $members, $desc]) {
        expect($user->can('view', $committee))->toBe($view, "$who view")
            ->and($user->can('create', [Committee::class, $event]))->toBe($create, "$who create")
            ->and($user->can('update', $committee))->toBe($update, "$who update")
            ->and($user->can('delete', $committee))->toBe($delete, "$who delete")
            ->and($user->can('manageMembers', $committee))->toBe($members, "$who manageMembers")
            ->and($user->can('updateDescription', $committee))->toBe($desc, "$who updateDescription");
    }
});

// ------------------------------------------------------------- Livewire

it('forbids strangers from loading the committees component', function () {
    $owner = User::factory()->organizational()->create();
    $stranger = User::factory()->create();
    $event = makeEvent($owner);

    Livewire::actingAs($stranger)
        ->test(EventCommittees::class, ['event' => $event])
        ->assertForbidden();
});

it('lets the owner create a committee from the UI', function () {
    $owner = User::factory()->organizational()->create();
    $event = makeEvent($owner);

    Livewire::actingAs($owner)
        ->test(EventCommittees::class, ['event' => $event])
        ->call('openCreate')
        ->set('type', 'finance')
        ->assertSet('name', CommitteeType::Finance->label())
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee(CommitteeType::Finance->label());

    expect($event->committees()->count())->toBe(1);
});

it('shows a limit message instead of crashing when the cap is reached', function () {
    $owner = User::factory()->individual()->create();
    $event = makeEvent($owner);
    $svc = app(CommitteeService::class);
    foreach (range(1, 3) as $i) {
        $svc->create($event, $owner, ['name' => "c$i"]);
    }

    Livewire::actingAs($owner)
        ->test(EventCommittees::class, ['event' => $event])
        ->call('openCreate')
        ->set('name', 'چهارم')
        ->call('save')
        ->assertSet('error', __('account.limit_exceeded_detail', [
            'limit' => __('account.limits.committees_per_event'),
            'max' => 3,
        ]));

    expect($event->committees()->count())->toBe(3);
});

it('forbids a plain member from creating or deleting committees', function () {
    $owner = User::factory()->organizational()->create();
    $member = User::factory()->create();
    $event = makeEvent($owner);
    joinEvent($event, $member);
    $committee = app(CommitteeService::class)->create($event, $owner, ['name' => 'فنی']);

    Livewire::actingAs($member)
        ->test(EventCommittees::class, ['event' => $event])
        ->call('openCreate')
        ->assertForbidden();

    Livewire::actingAs($member)
        ->test(EventCommittees::class, ['event' => $event])
        ->call('delete', $committee->id)
        ->assertForbidden();
});

it('lets the committee head edit the description of their own committee only', function () {
    $owner = User::factory()->organizational()->create();
    $head = User::factory()->create();
    $event = makeEvent($owner);
    joinEvent($event, $head);
    $svc = app(CommitteeService::class);
    $mine = $svc->create($event, $owner, ['name' => 'مالی']);
    $other = $svc->create($event, $owner, ['name' => 'فنی']);
    $svc->addMember($mine, $owner, $head);
    $svc->setHead($mine, $head);

    Livewire::actingAs($head)
        ->test(EventCommittees::class, ['event' => $event])
        ->call('select', $mine->id)
        ->set('descriptionDraft', 'بودجه‌بندی و گزارش')
        ->call('saveDescription')
        ->assertHasNoErrors();

    expect($mine->fresh()->description)->toBe('بودجه‌بندی و گزارش');

    Livewire::actingAs($head)
        ->test(EventCommittees::class, ['event' => $event])
        ->call('select', $other->id)
        ->set('descriptionDraft', 'نباید ذخیره شود')
        ->call('saveDescription')
        ->assertForbidden();
});

it('cannot touch a committee that belongs to another event', function () {
    $ownerA = User::factory()->organizational()->create();
    $ownerB = User::factory()->organizational()->create();
    $eventA = makeEvent($ownerA);
    $eventB = makeEvent($ownerB);
    $foreign = app(CommitteeService::class)->create($eventB, $ownerB, ['name' => 'محرمانه']);

    Livewire::actingAs($ownerA)
        ->test(EventCommittees::class, ['event' => $eventA])
        ->call('select', $foreign->id)
        ->assertNotFound();

    expect($foreign->fresh()->name)->toBe('محرمانه');
});

it('renders the committees component inside the event hub tab', function () {
    $owner = User::factory()->organizational()->create();
    $event = makeEvent($owner);

    Livewire::actingAs($owner)
        ->test(EventShow::class, ['event' => $event])
        ->call('setTab', 'committees')
        ->assertSeeLivewire(EventCommittees::class);
});


// ------------------------------------------- merged from the first draft

it('rejects empty and duplicate committee names but allows reuse after delete', function () {
    $owner = User::factory()->organizational()->create();
    $event = makeEvent($owner);
    $svc = app(CommitteeService::class);

    expect(fn () => $svc->create($event, $owner, ['name' => '   ']))
        ->toThrow(InvalidArgumentException::class, __('committee.name_required'));

    $first = $svc->create($event, $owner, ['name' => 'مالی']);

    expect(fn () => $svc->create($event, $owner, ['name' => ' مالی ']))
        ->toThrow(InvalidArgumentException::class, __('committee.name_duplicate'));

    $svc->delete($first);
    expect($svc->create($event, $owner, ['name' => 'مالی'])->name)->toBe('مالی');
});

it('lets a committee be renamed without tripping its own duplicate check', function () {
    $owner = User::factory()->organizational()->create();
    $event = makeEvent($owner);
    $svc = app(CommitteeService::class);
    $committee = $svc->create($event, $owner, ['name' => 'مالی']);

    $svc->update($committee, ['name' => 'مالی', 'description' => 'شرح'], $owner);

    expect($committee->fresh()->description)->toBe('شرح');
});

it('assigns the head chosen in the create form, adding them to the event if needed', function () {
    $owner = User::factory()->organizational()->create();
    $newcomer = User::factory()->create();
    $event = makeEvent($owner);

    $committee = app(CommitteeService::class)->create($event, $owner, [
        'name' => 'فنی',
        'head_user_id' => $newcomer->id,
    ]);

    expect($event->hasMember($newcomer))->toBeTrue()
        ->and($committee->head_user_id)->toBe($newcomer->id) // returned model is not stale
        ->and($committee->fresh()->head_user_id)->toBe($newcomer->id)
        ->and($committee->roleOf($newcomer))->toBe(CommitteeRole::Head)
        ->and($event->roleOf($newcomer))->toBe(EventRole::CommitteeHead);
});

it('clears the head from the edit form', function () {
    $owner = User::factory()->organizational()->create();
    $head = User::factory()->create();
    $event = makeEvent($owner);
    joinEvent($event, $head);
    $svc = app(CommitteeService::class);
    $committee = $svc->create($event, $owner, ['name' => 'فنی', 'head_user_id' => $head->id]);

    $svc->update($committee, ['head_user_id' => null], $owner);

    expect($committee->fresh()->head_user_id)->toBeNull()
        ->and($committee->roleOf($head))->toBe(CommitteeRole::Member)
        ->and($event->roleOf($head))->toBe(EventRole::Member);
});

it('redirects the committees URL to the hub tab and keeps it private', function () {
    $owner = User::factory()->organizational()->create();
    $stranger = User::factory()->organizational()->create();
    $event = makeEvent($owner);

    $this->actingAs($owner)
        ->get(route('events.committees', $event))
        ->assertRedirect(route('events.show', ['event' => $event, 'tab' => 'committees']));

    $this->actingAs($stranger)
        ->get(route('events.committees', $event))
        ->assertForbidden();
});

it('opens the hub on the committees tab from ?tab=', function () {
    $owner = User::factory()->organizational()->create();
    $event = makeEvent($owner);

    Livewire::withQueryParams(['tab' => 'committees'])
        ->actingAs($owner)
        ->test(EventShow::class, ['event' => $event])
        ->assertSet('tab', 'committees');
});
