<?php

use App\Enums\EventRole;
use App\Jobs\SendSmsMessage;
use App\Livewire\Events\EventControlCenter;
use App\Models\EventAnnouncement;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

function an_scene(): array
{
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $member = User::factory()->create();
    tk_join($event, $member);

    return [$owner, $event, $member];
}

it('lets the event manager announce to every member except themself', function () {
    [$owner, $event, $member] = an_scene();
    $outsider = User::factory()->create();

    Livewire::actingAs($owner)->test(EventControlCenter::class, ['event' => $event])
        ->set('announcement', 'جلسه‌ی هماهنگی ساعت ۱۰ در سالن اصلی')
        ->call('publish')
        ->assertSet('error', null)
        ->assertSet('announcement', '');

    expect(EventAnnouncement::query()->where('event_id', $event->id)->count())->toBe(1)
        ->and($member->notifications()->first()->data['type'])->toBe('event.announcement')
        ->and($owner->notifications()->count())->toBe(0)
        ->and($outsider->notifications()->count())->toBe(0);
});

it('forbids members and viewers from announcing but lets them read the center', function () {
    [$owner, $event, $member] = an_scene();
    $viewer = User::factory()->create();
    tk_join($event, $viewer, EventRole::Viewer);

    foreach ([$member, $viewer] as $user) {
        Livewire::actingAs($user)->test(EventControlCenter::class, ['event' => $event])
            ->assertOk()
            ->set('announcement', 'متن آزمایشی')
            ->call('publish')
            ->assertForbidden();
    }

    expect(EventAnnouncement::query()->count())->toBe(0);
});

it('blocks outsiders from the control center', function () {
    [, $event] = an_scene();

    Livewire::actingAs(User::factory()->create())->test(EventControlCenter::class, ['event' => $event])
        ->assertForbidden();
});

it('validates the text and requires an active event', function () {
    [$owner, $event] = an_scene();

    Livewire::actingAs($owner)->test(EventControlCenter::class, ['event' => $event])
        ->set('announcement', 'ا')
        ->call('publish')
        ->assertSet('error', __('notification.announcement_invalid'));

    $event->update(['status' => 'completed']);

    Livewire::actingAs($owner)->test(EventControlCenter::class, ['event' => $event->fresh()])
        ->set('announcement', 'متن معتبر برای اطلاعیه')
        ->call('publish')
        ->assertSet('error', __('notification.event_closed'));

    expect(EventAnnouncement::query()->count())->toBe(0);
});

it('sends SMS only for urgent announcements and caps them per event per day', function () {
    Queue::fake();
    config(['notifications.sms.announcement_per_event_per_day' => 1]);
    [$owner, $event] = an_scene();

    $component = Livewire::actingAs($owner)->test(EventControlCenter::class, ['event' => $event]);

    $component->set('announcement', 'اطلاعیه‌ی عادی')->call('publish');
    Queue::assertNotPushed(SendSmsMessage::class);

    $component->set('announcement', 'اطلاعیه‌ی فوری اول')->set('urgent', true)->call('publish');
    Queue::assertPushed(SendSmsMessage::class, 1);

    $component->set('announcement', 'اطلاعیه‌ی فوری دوم')->set('urgent', true)->call('publish')
        ->assertSet('error', __('notification.sms_cap_reached'));

    expect(EventAnnouncement::query()->count())->toBe(2);
});
