<?php

use App\Enums\CampaignStatus;
use App\Enums\EventRole;
use App\Livewire\Events\EventMarketing;
use App\Livewire\Events\EventShow;
use App\Models\ActivityLog;
use App\Models\CampaignItem;
use App\Models\Event;
use App\Models\User;
use App\Services\MarketingService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

function mk_service(): MarketingService
{
    return app(MarketingService::class);
}

function mk_item(Event $event, User $actor, array $data = []): CampaignItem
{
    return mk_service()->createItem($event, $actor, array_merge([
        'title' => 'پست معرفی '.fake()->unique()->numerify('###'),
        'phase' => 'pre',
        'channel' => 'social',
    ], $data));
}

// ------------------------------------------------------------- entitlement

it('rejects marketing writes for an individual account', function () {
    $owner = User::factory()->individual()->create();
    $event = tk_event($owner);

    expect(fn () => mk_item($event, $owner))->toThrow(InvalidArgumentException::class);
    expect(fn () => mk_service()->savePlan($event, $owner, ['message' => 'x']))->toThrow(InvalidArgumentException::class);
    expect(CampaignItem::query()->count())->toBe(0);
});

// -------------------------------------------------------------------- plan

it('keeps one plan per event and normalizes segments and channels', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);

    $plan = mk_service()->savePlan($event, $owner, [
        'target_segments' => "خانواده‌ها\n دانشجویان ،خانواده‌ها\n\n",
        'positioning' => 'رویداد ورزشی خانوادگی',
        'message' => 'با هم بدویم',
        'channels' => ['social', 'sms', 'social'],
    ]);

    expect($plan->target_segments)->toBe(['خانواده‌ها', 'دانشجویان'])
        ->and($plan->channels)->toBe(['social', 'sms']);

    mk_service()->savePlan($event, $owner, ['message' => 'پیام جدید']);

    expect(App\Models\MarketingPlan::query()->where('event_id', $event->id)->count())->toBe(1)
        ->and(mk_service()->plan($event)->message)->toBe('پیام جدید')
        ->and(mk_service()->plan($event)->positioning)->toBe('رویداد ورزشی خانوادگی');
});

it('validates plan input', function (array $bad) {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);

    expect(fn () => mk_service()->savePlan($event, $owner, $bad))->toThrow(InvalidArgumentException::class);
})->with([
    'unknown channel' => [['channels' => ['pigeon']]],
    'too many segments' => [['target_segments' => implode("\n", range(1, 13))]],
    'long segment' => [['target_segments' => str_repeat('ا', 81)]],
    'long message' => [['message' => str_repeat('ا', 2001)]],
]);

// ---------------------------------------------------------------- calendar

it('creates calendar items ordered by publish date with undated last', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);

    mk_item($event, $owner, ['title' => 'بدون تاریخ']);
    mk_item($event, $owner, ['title' => 'دیرتر', 'publish_at' => '2026-12-20 09:00']);
    mk_item($event, $owner, ['title' => 'زودتر', 'publish_at' => '2026-12-10 09:00']);

    expect(mk_service()->calendar($event)->pluck('title')->all())->toBe(['زودتر', 'دیرتر', 'بدون تاریخ']);

    expect(mk_service()->calendar($event, ['phase' => 'post']))->toHaveCount(0);
});

it('validates calendar items', function (array $bad) {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);

    expect(fn () => mk_item($event, $owner, $bad))->toThrow(InvalidArgumentException::class);
})->with([
    'empty title' => [['title' => ' ']],
    'long title' => [['title' => str_repeat('ا', 161)]],
    'bad phase' => [['phase' => 'later']],
    'bad channel' => [['channel' => 'pigeon']],
    'bad date' => [['publish_at' => 'not a date']],
    'target without metric' => [['kpi_target' => 100]],
    'zero target' => [['kpi_metric' => 'reach', 'kpi_target' => 0]],
    'negative target' => [['kpi_metric' => 'reach', 'kpi_target' => -5]],
]);

it('accepts only non-viewer members of the same event as owner', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $other = tk_event($owner);

    $member = User::factory()->organizational()->create();
    $viewer = User::factory()->organizational()->create();
    $outsider = User::factory()->organizational()->create();
    tk_join($event, $member, EventRole::Member);
    tk_join($event, $viewer, EventRole::Viewer);
    tk_join($other, $outsider, EventRole::Member);

    expect(mk_item($event, $owner, ['owner_id' => $member->id])->owner_id)->toBe($member->id);

    expect(fn () => mk_item($event, $owner, ['owner_id' => $viewer->id]))->toThrow(InvalidArgumentException::class);
    expect(fn () => mk_item($event, $owner, ['owner_id' => $outsider->id]))->toThrow(InvalidArgumentException::class);
});

it('changes status and writes an activity log', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $item = mk_item($event, $owner);

    $item = mk_service()->setItemStatus($item, $owner, CampaignStatus::Published);

    expect($item->status)->toBe(CampaignStatus::Published)
        ->and(ActivityLog::query()->where('action', 'campaign.status_changed')->count())->toBe(1);
});

it('flags a late item only while it is still open', function () {
    Carbon::setTestNow('2026-12-15 12:00:00');

    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);

    $late = mk_item($event, $owner, ['publish_at' => '2026-12-10 09:00']);
    $future = mk_item($event, $owner, ['publish_at' => '2026-12-20 09:00']);
    $published = mk_item($event, $owner, ['publish_at' => '2026-12-01 09:00']);
    mk_service()->setItemStatus($published, $owner, CampaignStatus::Published);

    expect($late->fresh()->isLate())->toBeTrue()
        ->and($future->fresh()->isLate())->toBeFalse()
        ->and($published->fresh()->isLate())->toBeFalse()
        ->and(mk_service()->summary($event)['late'])->toBe(1);

    Carbon::setTestNow();
});

// --------------------------------------------------------------------- KPI

it('records KPI actuals and aggregates per metric, skipping cancelled items', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);

    $a = mk_item($event, $owner, ['kpi_metric' => 'reach', 'kpi_target' => 1000]);
    $b = mk_item($event, $owner, ['kpi_metric' => 'reach', 'kpi_target' => 1000]);
    $c = mk_item($event, $owner, ['kpi_metric' => 'engagement', 'kpi_target' => 200]);
    $cancelled = mk_item($event, $owner, ['kpi_metric' => 'reach', 'kpi_target' => 5000]);
    mk_item($event, $owner, ['title' => 'بدون KPI']);

    mk_service()->recordKpi($a, $owner, '600');
    mk_service()->recordKpi($b, $owner, 900);
    mk_service()->recordKpi($c, $owner, 50);
    mk_service()->setItemStatus($cancelled, $owner, CampaignStatus::Cancelled);

    $kpi = mk_service()->summary($event)['kpi'];

    expect($kpi['reach'])->toBe(['items' => 2, 'target' => 2000, 'actual' => 1500, 'percent' => 75])
        ->and($kpi['engagement'])->toBe(['items' => 1, 'target' => 200, 'actual' => 50, 'percent' => 25])
        ->and($kpi['awareness'])->toBe(['items' => 0, 'target' => 0, 'actual' => 0, 'percent' => null])
        ->and($a->fresh()->kpiPercent())->toBe(60);
});

it('refuses KPI actuals without a metric or with a bad number', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);

    $plain = mk_item($event, $owner);
    expect(fn () => mk_service()->recordKpi($plain, $owner, 10))->toThrow(InvalidArgumentException::class);

    $tracked = mk_item($event, $owner, ['kpi_metric' => 'awareness', 'kpi_target' => 10]);
    expect(fn () => mk_service()->recordKpi($tracked, $owner, -1))->toThrow(InvalidArgumentException::class);
    expect(fn () => mk_service()->recordKpi($tracked, $owner, 'abc'))->toThrow(InvalidArgumentException::class);
    expect(fn () => mk_service()->recordKpi($tracked, $owner, ''))->toThrow(InvalidArgumentException::class);
});

it('clears target and actual when the metric is removed', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);

    $item = mk_item($event, $owner, ['kpi_metric' => 'reach', 'kpi_target' => 100]);
    mk_service()->recordKpi($item, $owner, 40);

    $item = mk_service()->updateItem($item->fresh(), $owner, ['kpi_metric' => null]);

    expect($item->kpi_metric)->toBeNull()
        ->and($item->kpi_target)->toBeNull()
        ->and($item->kpi_actual)->toBeNull();
});

// -------------------------------------------------------------- archived

it('rejects marketing writes on an archived event', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $item = mk_item($event, $owner);

    $event->forceFill(['status' => App\Enums\EventStatus::Archived])->save();

    expect(fn () => mk_service()->setItemStatus($item->fresh(), $owner, CampaignStatus::Published))
        ->toThrow(InvalidArgumentException::class);
});

// -------------------------------------------------------------- component

it('forbids strangers from opening the marketing tab', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $stranger = User::factory()->organizational()->create();

    Livewire::actingAs($stranger)->test(EventMarketing::class, ['event' => $event])->assertForbidden();
});

it('shows an organization-only notice to an individual account', function () {
    $owner = User::factory()->individual()->create();
    $event = tk_event($owner);

    Livewire::actingAs($owner)->test(EventMarketing::class, ['event' => $event])
        ->assertSee(__('marketing.org_only_title'));
});

it('lets a manager save the plan and manage calendar items and KPI', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);

    $component = Livewire::actingAs($owner)->test(EventMarketing::class, ['event' => $event])
        ->set('mSegments', "خانواده‌ها\nدانشجویان")
        ->set('mMessage', 'پیام اصلی')
        ->set('mChannels', ['social', 'email'])
        ->call('savePlan')
        ->assertHasNoErrors()
        ->assertSet('error', null);

    expect(mk_service()->plan($event)->channels)->toBe(['social', 'email']);

    $component->call('openItemForm', 'during')
        ->set('iTitle', 'پوشش زنده')
        ->set('iChannel', 'social')
        ->set('iDate', '2026-12-12')
        ->set('iTime', '۱۴:۳۰')
        ->set('iMetric', 'reach')
        ->set('iTarget', '۱۰۰۰')
        ->call('saveItem')
        ->assertHasNoErrors()
        ->assertSet('error', null);

    $item = CampaignItem::query()->firstOrFail();
    expect($item->phase->value)->toBe('during')
        ->and($item->publish_at->format('H:i'))->toBe('14:30')
        ->and($item->kpi_target)->toBe(1000);

    $component->call('startKpi', $item->id)->set('kpiActual', '250')->call('saveKpi')->assertSet('error', null);
    expect($item->fresh()->kpi_actual)->toBe(250);

    $component->call('changeItemStatus', $item->id, 'published');
    expect($item->fresh()->status)->toBe(CampaignStatus::Published);

    $component->call('deleteItem', $item->id);
    expect(CampaignItem::query()->count())->toBe(0);
});

it('lets a member read but not change marketing', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $member = User::factory()->organizational()->create();
    tk_join($event, $member, EventRole::Member);

    $item = mk_item($event, $owner, ['title' => 'آیتم قابل‌دیدن']);

    Livewire::actingAs($member)->test(EventMarketing::class, ['event' => $event])->assertSee('آیتم قابل‌دیدن');

    Livewire::actingAs($member)->test(EventMarketing::class, ['event' => $event])
        ->call('deleteItem', $item->id)->assertForbidden();
    Livewire::actingAs($member)->test(EventMarketing::class, ['event' => $event])
        ->call('savePlan')->assertForbidden();

    expect(CampaignItem::query()->count())->toBe(1);
});

it('does not touch campaign items of another event', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $other = tk_event($owner);
    $foreign = mk_item($other, $owner);

    $component = Livewire::actingAs($owner)->test(EventMarketing::class, ['event' => $event]);

    foreach ([
        fn () => $component->call('editItem', $foreign->id),
        fn () => $component->call('deleteItem', $foreign->id),
        fn () => $component->call('changeItemStatus', $foreign->id, 'published'),
        fn () => $component->call('startKpi', $foreign->id),
    ] as $action) {
        try {
            $action();
        } catch (ModelNotFoundException|Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
            // expected
        }
    }

    expect($foreign->fresh())->not->toBeNull()
        ->and($foreign->fresh()->status)->toBe(CampaignStatus::Planned);
});

it('mounts the marketing tab from the event hub', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);

    Livewire::actingAs($owner)->test(EventShow::class, ['event' => $event])
        ->set('tab', 'marketing')
        ->assertSeeLivewire(EventMarketing::class);
});
