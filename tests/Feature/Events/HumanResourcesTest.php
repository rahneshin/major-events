<?php

use App\Enums\AccountType;
use App\Enums\EventRole;
use App\Exceptions\LimitExceededException;
use App\Livewire\App\MyAssignments;
use App\Livewire\Events\AcceptInvitation;
use App\Livewire\Events\EventHumanResources;
use App\Models\Event;
use App\Models\EventInvitation;
use App\Models\User;
use App\Services\CommitteeService;
use App\Services\HumanResourceService;
use App\Services\Sms\SmsGateway;
use App\Support\PhoneNumber;
use Livewire\Livewire;

/** SMS جعلی که پیام‌ها را نگه می‌دارد. */
function hr_fakeSms(): object
{
    $fake = new class implements SmsGateway
    {
        /** @var list<array{phone: string, message: string}> */
        public array $sent = [];

        public function send(string $phone, string $message): bool
        {
            $this->sent[] = ['phone' => $phone, 'message' => $message];

            return true;
        }
    };

    app()->instance(SmsGateway::class, $fake);

    return $fake;
}

function hr_event(User $owner): Event
{
    return Event::query()->create([
        'created_by' => $owner->id,
        'title' => 'رویداد '.fake()->unique()->numerify('####'),
        'event_type' => \App\Enums\EventType::Local,
        'scale' => 'small',
        'status' => 'draft',
    ]);
}

/** کاربری که تازه با OTP وارد شده: بدون نام و بدون نوع حساب. */
function hr_freshUser(string $phone): User
{
    return User::query()->create([
        'phone' => PhoneNumber::normalize($phone),
        'status' => 'active',
        'phone_verified_at' => now(),
    ]);
}

it('invites an unknown phone with a committee and post, texts a link, and creates no user yet', function () {
    $sms = hr_fakeSms();
    $owner = User::factory()->organizational()->create();
    $event = hr_event($owner);
    $committee = app(CommitteeService::class)->create($event, $owner, ['name' => 'پذیرایی']);

    $result = app(HumanResourceService::class)->enroll(
        $event, $owner, '09121234567', 'علی رضایی', EventRole::Member, $committee, 'مسئول پذیرایی',
    );

    $invitation = $result['invitation'];

    expect($result['status'])->toBe('invited')
        ->and(User::query()->where('phone', PhoneNumber::normalize('09121234567'))->exists())->toBeFalse()
        ->and($invitation->status)->toBe('pending')
        ->and($invitation->committee_id)->toBe($committee->id)
        ->and($invitation->post)->toBe('مسئول پذیرایی')
        ->and($sms->sent)->toHaveCount(1)
        ->and($sms->sent[0]['message'])
        ->toContain($event->title)
        ->toContain('پذیرایی')
        ->toContain(route('events.invitation', ['token' => $invitation->plainToken]));
});

it('re-inviting the same phone keeps one pending row and invalidates the old link', function () {
    hr_fakeSms();
    $owner = User::factory()->organizational()->create();
    $event = hr_event($owner);
    $svc = app(HumanResourceService::class);

    $first = $svc->enroll($event, $owner, '09121234567')['invitation'];
    $oldToken = $first->plainToken;
    $second = $svc->enroll($event, $owner, '09121234567')['invitation'];

    expect(EventInvitation::query()->where('event_id', $event->id)->where('status', 'pending')->count())->toBe(1)
        ->and($second->id)->toBe($first->id)
        ->and(EventInvitation::findByToken($oldToken))->toBeNull()
        ->and(EventInvitation::findByToken($second->plainToken)?->id)->toBe($first->id);
});

it('makes the person a member with post and default account type when they click the link', function () {
    hr_fakeSms();
    $owner = User::factory()->organizational()->create();
    $event = hr_event($owner);
    $committee = app(CommitteeService::class)->create($event, $owner, ['name' => 'پذیرایی']);

    $token = app(HumanResourceService::class)->enroll(
        $event, $owner, '09127778888', 'نیما', EventRole::Member, $committee, 'مسئول پذیرایی',
    )['invitation']->plainToken;

    $user = hr_freshUser('09127778888');

    Livewire::actingAs($user)
        ->test(AcceptInvitation::class, ['token' => $token])
        ->assertRedirect(route('app.dashboard'));

    $user->refresh();

    expect($event->hasMember($user))->toBeTrue()
        ->and($user->name)->toBe('نیما')
        ->and($user->account_type)->toBe(AccountType::Individual)
        ->and($user->isOnboarded())->toBeTrue()
        ->and($committee->fresh()->members()->where('users.id', $user->id)->first()->pivot->post)->toBe('مسئول پذیرایی')
        ->and(EventInvitation::findByToken($token)->status)->toBe('accepted');
});

it('sends a guest to login and remembers the invitation', function () {
    hr_fakeSms();
    $owner = User::factory()->organizational()->create();
    $event = hr_event($owner);

    $token = app(HumanResourceService::class)->enroll($event, $owner, '09127778888')['invitation']->plainToken;

    Livewire::test(AcceptInvitation::class, ['token' => $token])
        ->assertRedirect(route('auth.login'));

    expect(session('pending_invite_token'))->toBe($token)
        ->and(session('invite_phone'))->toBe(PhoneNumber::normalize('09127778888'));
});

it('refuses a different phone, a used link and a revoked link', function () {
    hr_fakeSms();
    $owner = User::factory()->organizational()->create();
    $event = hr_event($owner);
    $svc = app(HumanResourceService::class);

    $invitation = $svc->enroll($event, $owner, '09127778888')['invitation'];
    $token = $invitation->plainToken;

    $other = hr_freshUser('09120000000');
    Livewire::actingAs($other)->test(AcceptInvitation::class, ['token' => $token])
        ->assertSee(__('hr.invite_phone_mismatch'));
    expect($event->hasMember($other))->toBeFalse();

    $right = hr_freshUser('09127778888');
    Livewire::actingAs($right)->test(AcceptInvitation::class, ['token' => $token]);
    Livewire::actingAs($right)->test(AcceptInvitation::class, ['token' => $token])
        ->assertSee(__('hr.invite_invalid'));

    $second = $svc->enroll($event, $owner, '09125550000')['invitation'];
    $svc->revoke($second);
    Livewire::actingAs(hr_freshUser('09125550000'))->test(AcceptInvitation::class, ['token' => $second->plainToken])
        ->assertSee(__('hr.invite_invalid'));
});

it('assigns an existing event member directly, without a link', function () {
    $sms = hr_fakeSms();
    $owner = User::factory()->organizational()->create();
    $event = hr_event($owner);
    $committee = app(CommitteeService::class)->create($event, $owner, ['name' => 'فنی']);

    $member = hr_freshUser('09127778888');
    $event->members()->attach($member->id, ['role' => 'member', 'added_by' => $owner->id, 'joined_at' => now()]);

    $result = app(HumanResourceService::class)->enroll(
        $event, $owner, '09127778888', null, EventRole::Member, $committee, 'مسئول فنی',
    );

    expect($result['status'])->toBe('assigned')
        ->and($result['invitation'])->toBeNull()
        ->and($committee->fresh()->members()->where('users.id', $member->id)->first()->pivot->post)->toBe('مسئول فنی')
        ->and($sms->sent)->toHaveCount(1);
});

it('does not let a viewer hold a committee post', function () {
    hr_fakeSms();
    $owner = User::factory()->organizational()->create();
    $event = hr_event($owner);
    $committee = app(CommitteeService::class)->create($event, $owner, ['name' => 'فنی']);

    app(HumanResourceService::class)->enroll(
        $event, $owner, '09121234567', null, EventRole::Viewer, $committee, 'x',
    );
})->throws(InvalidArgumentException::class);

it('refuses to invite beyond the plan member limit', function () {
    hr_fakeSms();
    $owner = User::factory()->individual()->create(); // members_per_event = 10 (مالک هم شمرده می‌شود)
    $event = hr_event($owner);

    foreach (range(1, 9) as $i) {
        $u = hr_freshUser('0912000000'.$i);
        $event->members()->attach($u->id, ['role' => 'member', 'added_by' => $owner->id, 'joined_at' => now()]);
    }

    app(HumanResourceService::class)->enroll($event, $owner, '09129999999');
})->throws(LimitExceededException::class);

it('lets an event owner invite from the HR tab and blocks plain members', function () {
    hr_fakeSms();
    $owner = User::factory()->organizational()->create();
    $event = hr_event($owner);
    $committee = app(CommitteeService::class)->create($event, $owner, ['name' => 'اجرایی']);

    Livewire::actingAs($owner)
        ->test(EventHumanResources::class, ['event' => $event])
        ->set('phone', '09123334444')
        ->set('name', 'سارا')
        ->set('committeeId', (string) $committee->id)
        ->set('post', 'هماهنگ‌کننده')
        ->call('enroll')
        ->assertHasNoErrors()
        ->assertSee('سارا')
        ->assertSee('هماهنگ‌کننده');

    expect($event->invitations()->where('status', 'pending')->count())->toBe(1);

    $member = User::factory()->individual()->create();
    $event->members()->attach($member->id, ['role' => 'member', 'added_by' => $owner->id, 'joined_at' => now()]);

    Livewire::actingAs($member)
        ->test(EventHumanResources::class, ['event' => $event])
        ->set('phone', '09125556666')
        ->call('enroll')
        ->assertForbidden();
});

it('shows a member their post and open tasks on the panel after accepting', function () {
    hr_fakeSms();
    $owner = User::factory()->organizational()->create();
    $event = hr_event($owner);
    $committee = app(CommitteeService::class)->create($event, $owner, ['name' => 'پذیرایی']);

    $token = app(HumanResourceService::class)->enroll(
        $event, $owner, '09127778888', 'نیما', EventRole::Member, $committee, 'مسئول پذیرایی',
    )['invitation']->plainToken;

    $user = hr_freshUser('09127778888');
    Livewire::actingAs($user)->test(AcceptInvitation::class, ['token' => $token]);

    tk_task($event, $owner, [
        'title' => 'سفارش غذا',
        'committee_id' => $committee->id,
        'assignee_id' => $user->id,
    ]);

    Livewire::actingAs($user->fresh())
        ->test(MyAssignments::class)
        ->assertSee('مسئول پذیرایی')
        ->assertSee('سفارش غذا');
});
