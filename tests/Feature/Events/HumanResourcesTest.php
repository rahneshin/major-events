<?php

use App\Enums\EventRole;
use App\Livewire\App\MyAssignments;
use App\Livewire\Events\EventHumanResources;
use App\Models\Event;
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

it('registers an unknown phone, adds it to the event and committee with a post, and sends an SMS', function () {
    $sms = hr_fakeSms();
    $owner = User::factory()->organizational()->create();
    $event = hr_event($owner);
    $committee = app(CommitteeService::class)->create($event, $owner, ['name' => 'پذیرایی']);

    $result = app(HumanResourceService::class)->enroll(
        $event, $owner, '09121234567', 'علی رضایی', EventRole::Member, $committee, 'مسئول پذیرایی',
    );

    $user = User::query()->where('phone', PhoneNumber::normalize('09121234567'))->first();

    expect($result['created'])->toBeTrue()
        ->and($user)->not->toBeNull()
        ->and($user->name)->toBe('علی رضایی')
        ->and($event->hasMember($user))->toBeTrue()
        ->and($committee->fresh()->members()->where('users.id', $user->id)->first()->pivot->post)->toBe('مسئول پذیرایی')
        ->and($sms->sent)->toHaveCount(1)
        ->and($sms->sent[0]['phone'])->toBe($user->phone)
        ->and($sms->sent[0]['message'])->toContain($event->title)->toContain('پذیرایی')->toContain('مسئول پذیرایی');
});

it('is idempotent for someone already in the event and committee: updates the post, sends no new SMS', function () {
    $sms = hr_fakeSms();
    $owner = User::factory()->organizational()->create();
    $event = hr_event($owner);
    $committee = app(CommitteeService::class)->create($event, $owner, ['name' => 'فنی']);
    $svc = app(HumanResourceService::class);

    $svc->enroll($event, $owner, '09121234567', null, EventRole::Member, $committee, 'کمک‌فنی');
    $svc->enroll($event, $owner, '09121234567', null, EventRole::Member, $committee, 'مسئول فنی');

    $user = User::query()->where('phone', PhoneNumber::normalize('09121234567'))->first();

    expect($event->members()->where('users.id', $user->id)->count())->toBe(1)
        ->and($committee->fresh()->members()->where('users.id', $user->id)->first()->pivot->post)->toBe('مسئول فنی')
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

it('does not leave an orphan user behind when the member limit is hit', function () {
    hr_fakeSms();
    $owner = User::factory()->individual()->create(); // members_per_event = 10
    $event = hr_event($owner);

    foreach (range(1, 9) as $i) {
        app(HumanResourceService::class)->enroll($event, $owner, '0912000000'.$i);
    }

    $before = User::query()->count();

    try {
        app(HumanResourceService::class)->enroll($event, $owner, '09129999999');
    } catch (\Throwable) {
    }

    expect(User::query()->count())->toBe($before);
});

it('lets an event owner enroll from the HR tab and blocks plain members', function () {
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

    $member = User::factory()->individual()->create();
    $event->members()->attach($member->id, ['role' => 'member', 'added_by' => $owner->id, 'joined_at' => now()]);

    Livewire::actingAs($member)
        ->test(EventHumanResources::class, ['event' => $event])
        ->set('phone', '09125556666')
        ->call('enroll')
        ->assertForbidden();
});

it('shows a member their post and open tasks on the panel', function () {
    hr_fakeSms();
    $owner = User::factory()->organizational()->create();
    $event = hr_event($owner);
    $committee = app(CommitteeService::class)->create($event, $owner, ['name' => 'پذیرایی']);

    $result = app(HumanResourceService::class)->enroll(
        $event, $owner, '09127778888', 'نیما', EventRole::Member, $committee, 'مسئول پذیرایی',
    );

    tk_task($event, $owner, [
        'title' => 'سفارش غذا',
        'committee_id' => $committee->id,
        'assignee_id' => $result['user']->id,
    ]);

    Livewire::actingAs($result['user'])
        ->test(MyAssignments::class)
        ->assertSee('مسئول پذیرایی')
        ->assertSee('سفارش غذا');
});
