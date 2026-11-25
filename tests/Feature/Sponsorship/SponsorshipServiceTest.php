<?php

use App\Enums\DeliverableStatus;
use App\Enums\EventStatus;
use App\Enums\SponsorStatus;
use App\Enums\TaskModule;
use App\Enums\TaskPhase;
use App\Models\ActivityLog;
use App\Models\Sponsor;
use App\Models\Task;
use App\Models\User;
use App\Services\SponsorshipService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

// ------------------------------------------------------------- entitlement

it('rejects every sponsorship write for an individual account', function () {
    $owner = User::factory()->individual()->create();
    $event = tk_event($owner);

    expect(sp_service()->hasFeature($owner))->toBeFalse();

    expect(fn () => sp_sponsor($event, $owner))->toThrow(InvalidArgumentException::class);
    expect(fn () => sp_asset($event, $owner))->toThrow(InvalidArgumentException::class);
    expect(Sponsor::query()->count())->toBe(0);
});

it('rejects writes on archived or cancelled events', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $sponsor = sp_sponsor($event, $owner);

    $event->forceFill(['status' => EventStatus::Archived])->save();

    expect(fn () => sp_sponsor($event->fresh(), $owner))->toThrow(InvalidArgumentException::class);
    expect(fn () => sp_service()->transitionSponsor($sponsor->fresh(), $owner, SponsorStatus::Negotiating))
        ->toThrow(InvalidArgumentException::class);
});

// ----------------------------------------------------------------- sponsors

it('creates a sponsor as prospect with normalized fields and an activity log', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);

    $sponsor = sp_sponsor($event, $owner, [
        'name' => '  بانک نمونه  ',
        'contact_phone' => '۰۲۱-۱۲۳ ۴۵۶۷۸',
        'cash_value' => '2500000',
    ]);

    expect($sponsor->name)->toBe('بانک نمونه')
        ->and($sponsor->status)->toBe(SponsorStatus::Prospect)
        ->and($sponsor->contact_phone)->toBe('02112345678')
        ->and($sponsor->cash_value)->toBe(2_500_000)
        ->and($sponsor->totalValue())->toBe(3_000_000)
        ->and($sponsor->event_id)->toBe($event->id);

    expect(ActivityLog::query()->where('action', 'sponsor.created')->count())->toBe(1);
});

it('validates sponsor input', function (array $bad) {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);

    expect(fn () => sp_sponsor($event, $owner, $bad))->toThrow(InvalidArgumentException::class);
})->with([
    'empty name' => [['name' => '   ']],
    'long name' => [['name' => str_repeat('ا', 161)]],
    'negative cash' => [['cash_value' => -1]],
    'float cash' => [['cash_value' => 10.5]],
    'text cash' => [['cash_value' => 'abc']],
    'huge cash' => [['cash_value' => 99_999_999_999_999]],
    'bad phone' => [['contact_phone' => '12ab']],
    'bad category' => [['category' => 'nope']],
]);

it('follows the sponsor status flow and blocks illegal jumps', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $sponsor = sp_sponsor($event, $owner);

    expect(fn () => sp_service()->transitionSponsor($sponsor, $owner, SponsorStatus::Delivered))
        ->toThrow(InvalidArgumentException::class);

    $sponsor = sp_service()->transitionSponsor($sponsor, $owner, SponsorStatus::Negotiating);
    $sponsor = sp_service()->transitionSponsor($sponsor, $owner, SponsorStatus::Signed);
    $sponsor = sp_service()->transitionSponsor($sponsor, $owner, SponsorStatus::Delivered);

    expect($sponsor->status)->toBe(SponsorStatus::Delivered);
    expect(ActivityLog::query()->where('action', 'sponsor.status_changed')->count())->toBe(3);
});

it('does not mark a sponsor delivered while a deliverable is still open', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $sponsor = sp_sponsor($event, $owner, ['name' => 'الف']);
    $sponsor = sp_service()->transitionSponsor($sponsor, $owner, SponsorStatus::Signed);

    $deliverable = sp_deliverable($sponsor, $owner);

    expect(fn () => sp_service()->transitionSponsor($sponsor, $owner, SponsorStatus::Delivered))
        ->toThrow(InvalidArgumentException::class);

    sp_service()->setDeliverableStatus($deliverable, $owner, DeliverableStatus::Delivered);

    expect(sp_service()->transitionSponsor($sponsor->fresh(), $owner, SponsorStatus::Delivered)->status)
        ->toBe(SponsorStatus::Delivered);
});

it('cancels open deliverables and frees inventory when a sponsor is lost', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $sponsor = sp_sponsor($event, $owner);
    $asset = sp_asset($event, $owner, ['inventory' => 2]);

    $open = sp_deliverable($sponsor, $owner, ['asset_id' => $asset->id, 'quantity' => 2]);
    $done = sp_deliverable($sponsor, $owner, ['commitment' => 'دیگر']);
    sp_service()->setDeliverableStatus($done, $owner, DeliverableStatus::Delivered);

    expect($asset->fresh()->remainingQuantity())->toBe(0);

    sp_service()->transitionSponsor($sponsor, $owner, SponsorStatus::Lost);

    expect($open->fresh()->status)->toBe(DeliverableStatus::Cancelled)
        ->and($done->fresh()->status)->toBe(DeliverableStatus::Delivered)
        ->and($asset->fresh()->remainingQuantity())->toBe(2);

    // اسپانسر از دست رفته تعهد تازه نمی‌گیرد
    expect(fn () => sp_deliverable($sponsor->fresh(), $owner))->toThrow(InvalidArgumentException::class);
});

it('soft deletes a sponsor together with its deliverables', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $sponsor = sp_sponsor($event, $owner);
    $deliverable = sp_deliverable($sponsor, $owner);

    sp_service()->deleteSponsor($sponsor, $owner);

    expect(Sponsor::query()->count())->toBe(0)
        ->and(SponsorDeliverable::query()->count())->toBe(0)
        ->and(SponsorDeliverable::withTrashed()->whereKey($deliverable->id)->exists())->toBeTrue();
});

// ------------------------------------------------------- assets & packages

it('enforces asset inventory across deliverables', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $sponsor = sp_sponsor($event, $owner);
    $asset = sp_asset($event, $owner, ['inventory' => 3]);

    $first = sp_deliverable($sponsor, $owner, ['asset_id' => $asset->id, 'quantity' => 2]);

    expect(fn () => sp_deliverable($sponsor, $owner, ['asset_id' => $asset->id, 'quantity' => 2]))
        ->toThrow(InvalidArgumentException::class);

    sp_deliverable($sponsor, $owner, ['asset_id' => $asset->id, 'quantity' => 1]);
    expect($asset->fresh()->remainingQuantity())->toBe(0);

    // لغو، موجودی را آزاد می‌کند؛ بازکردن دوباره موجودی را می‌سنجد
    sp_service()->setDeliverableStatus($first, $owner, DeliverableStatus::Cancelled);
    expect($asset->fresh()->remainingQuantity())->toBe(2);

    sp_deliverable($sponsor, $owner, ['asset_id' => $asset->id, 'quantity' => 2]);

    expect(fn () => sp_service()->setDeliverableStatus($first->fresh(), $owner, DeliverableStatus::Pending))
        ->toThrow(InvalidArgumentException::class);
});

it('refuses to shrink inventory below what is allocated and to delete an in-use asset', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $sponsor = sp_sponsor($event, $owner);
    $asset = sp_asset($event, $owner, ['inventory' => 4]);
    sp_deliverable($sponsor, $owner, ['asset_id' => $asset->id, 'quantity' => 3]);

    expect(fn () => sp_service()->updateAsset($asset, $owner, ['inventory' => 2]))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => sp_service()->deleteAsset($asset, $owner))
        ->toThrow(InvalidArgumentException::class);

    expect(sp_service()->updateAsset($asset, $owner, ['inventory' => 3])->inventory)->toBe(3);
});

it('builds packages from assets of the same event only and generates deliverables from them', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $other = tk_event($owner);

    $a1 = sp_asset($event, $owner, ['name' => 'نام‌گذاری', 'inventory' => 1, 'price' => 5_000_000]);
    $a2 = sp_asset($event, $owner, ['name' => 'تابلو', 'inventory' => 10, 'price' => 100_000]);
    $foreign = sp_asset($other, $owner);

    expect(fn () => sp_service()->createPackage($event, $owner, [
        'name' => 'طلایی', 'price' => 9_000_000, 'assets' => [$foreign->id => 1],
    ]))->toThrow(InvalidArgumentException::class);

    $package = sp_service()->createPackage($event, $owner, [
        'name' => 'طلایی', 'price' => 9_000_000, 'assets' => [$a1->id => 1, $a2->id => 4],
    ]);

    expect($package->assets)->toHaveCount(2)
        ->and($package->assetsValue())->toBe(5_000_000 + 4 * 100_000);

    $sponsor = sp_sponsor($event, $owner, ['package_id' => $package->id]);

    $created = sp_service()->generateDeliverablesFromPackage($sponsor, $owner);
    expect($created)->toHaveCount(2)
        ->and($a2->fresh()->remainingQuantity())->toBe(6);

    // دوباره اجرا ← چیز تازه‌ای ساخته نمی‌شود
    expect(sp_service()->generateDeliverablesFromPackage($sponsor->fresh(), $owner))->toHaveCount(0);

    // اسپانسر بدون بسته
    $plain = sp_sponsor($event, $owner);
    expect(fn () => sp_service()->generateDeliverablesFromPackage($plain, $owner))
        ->toThrow(InvalidArgumentException::class);
});

it('rolls back the whole package generation when one asset has no stock', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);

    $plenty = sp_asset($event, $owner, ['inventory' => 5]);
    $scarce = sp_asset($event, $owner, ['inventory' => 1]);

    $package = sp_service()->createPackage($event, $owner, [
        'name' => 'بسته', 'assets' => [$plenty->id => 1, $scarce->id => 1],
    ]);

    // کس دیگری موجودی کمیاب را برداشته
    $first = sp_sponsor($event, $owner);
    sp_deliverable($first, $owner, ['asset_id' => $scarce->id, 'quantity' => 1]);

    $second = sp_sponsor($event, $owner, ['package_id' => $package->id]);

    expect(fn () => sp_service()->generateDeliverablesFromPackage($second, $owner))
        ->toThrow(InvalidArgumentException::class);

    expect($second->deliverables()->count())->toBe(0);
});

it('detaches sponsors when their package is deleted', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $package = sp_service()->createPackage($event, $owner, ['name' => 'نقره‌ای', 'price' => 1]);
    $sponsor = sp_sponsor($event, $owner, ['package_id' => $package->id]);

    sp_service()->deletePackage($package, $owner);

    expect($sponsor->fresh()->package_id)->toBeNull();
});

it('rejects a package from another event on a sponsor', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $other = tk_event($owner);
    $foreign = sp_service()->createPackage($other, $owner, ['name' => 'بیگانه']);

    expect(fn () => sp_sponsor($event, $owner, ['package_id' => $foreign->id]))
        ->toThrow(InvalidArgumentException::class);
});

// ------------------------------------------------------------ deliverables

it('falls back to the asset name when no commitment text is given', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $sponsor = sp_sponsor($event, $owner);
    $asset = sp_asset($event, $owner, ['name' => 'غرفه‌ی ویژه']);

    expect(sp_deliverable($sponsor, $owner, ['commitment' => '', 'asset_id' => $asset->id])->commitment)
        ->toBe('غرفه‌ی ویژه');

    expect(fn () => sp_deliverable($sponsor, $owner, ['commitment' => '']))
        ->toThrow(InvalidArgumentException::class);
});

it('detects overdue and due-soon deliverables and builds alerts', function () {
    Carbon::setTestNow('2026-12-01 10:00:00');

    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $sponsor = sp_sponsor($event, $owner);

    $late = sp_deliverable($sponsor, $owner, ['commitment' => 'معوق', 'due_at' => '2026-11-28']);
    $soon = sp_deliverable($sponsor, $owner, ['commitment' => 'نزدیک', 'due_at' => '2026-12-05']);
    $far = sp_deliverable($sponsor, $owner, ['commitment' => 'دور', 'due_at' => '2027-02-01']);
    $today = sp_deliverable($sponsor, $owner, ['commitment' => 'امروز', 'due_at' => '2026-12-01']);
    $done = sp_deliverable($sponsor, $owner, ['commitment' => 'انجام‌شده', 'due_at' => '2026-11-20']);
    sp_service()->setDeliverableStatus($done, $owner, DeliverableStatus::Delivered);

    expect($late->fresh()->isOverdue())->toBeTrue()
        ->and($today->fresh()->isOverdue())->toBeFalse()
        ->and($done->fresh()->isOverdue())->toBeFalse()
        ->and($soon->fresh()->isDueSoon(7))->toBeTrue()
        ->and($far->fresh()->isDueSoon(7))->toBeFalse();

    $alerts = sp_service()->alerts($event);
    expect($alerts['overdue']->pluck('commitment')->all())->toBe(['معوق'])
        ->and($alerts['due_soon']->pluck('commitment')->all())->toBe(['امروز', 'نزدیک']);

    $summary = sp_service()->summary($event);
    expect($summary['deliverables'])->toBe(['open' => 4, 'delivered' => 1, 'overdue' => 1, 'due_soon' => 2])
        ->and($summary['delivery_percent'])->toBe(20);

    expect(sp_service()->deliverables($event, ['overdue' => true])->pluck('commitment')->all())->toBe(['معوق']);

    Carbon::setTestNow();
});

it('stamps delivered_at and clears it when reopened', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $sponsor = sp_sponsor($event, $owner);
    $d = sp_deliverable($sponsor, $owner);

    $d = sp_service()->setDeliverableStatus($d, $owner, DeliverableStatus::Delivered);
    expect($d->delivered_at)->not->toBeNull();

    $d = sp_service()->setDeliverableStatus($d, $owner, DeliverableStatus::InProgress);
    expect($d->delivered_at)->toBeNull();
});

it('does not reopen a deliverable under a delivered sponsor', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $sponsor = sp_sponsor($event, $owner);
    $sponsor = sp_service()->transitionSponsor($sponsor, $owner, SponsorStatus::Signed);
    $d = sp_deliverable($sponsor, $owner);
    sp_service()->setDeliverableStatus($d, $owner, DeliverableStatus::Delivered);
    sp_service()->transitionSponsor($sponsor->fresh(), $owner, SponsorStatus::Delivered);

    expect(fn () => sp_service()->setDeliverableStatus($d->fresh(), $owner, DeliverableStatus::Pending))
        ->toThrow(InvalidArgumentException::class);
});

// ----------------------------------------------------------------- summary

it('sums committed and pipeline values by status', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);

    $signed = sp_sponsor($event, $owner, ['cash_value' => 1_000, 'in_kind_value' => 200]);
    sp_service()->transitionSponsor($signed, $owner, SponsorStatus::Signed);

    sp_sponsor($event, $owner, ['cash_value' => 50, 'in_kind_value' => 5]);          // prospect
    $lost = sp_sponsor($event, $owner, ['cash_value' => 9_999]);
    sp_service()->transitionSponsor($lost, $owner, SponsorStatus::Lost);

    $s = sp_service()->summary($event);

    expect($s['committed_cash'])->toBe(1_000)
        ->and($s['committed_in_kind'])->toBe(200)
        ->and($s['committed_total'])->toBe(1_200)
        ->and($s['pipeline_total'])->toBe(55)
        ->and($s['counts']['signed'])->toBe(1)
        ->and($s['counts']['lost'])->toBe(1)
        ->and($s['sponsor_total'])->toBe(3)
        ->and($s['delivery_percent'])->toBeNull();
});

// ---------------------------------------------------------------- evidence

it('stores evidence on the private disk, replaces and removes it', function () {
    Storage::fake('local');

    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $d = sp_deliverable(sp_sponsor($event, $owner), $owner);

    $d = sp_service()->attachEvidence($d, $owner, UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf'));
    $first = $d->evidence_path;

    expect($first)->toStartWith('sponsor-evidence/'.$event->id.'/');
    Storage::disk('local')->assertExists($first);

    $d = sp_service()->attachEvidence($d, $owner, UploadedFile::fake()->create('photo.png', 50, 'image/png'));
    Storage::disk('local')->assertMissing($first);
    Storage::disk('local')->assertExists($d->evidence_path);

    $path = $d->evidence_path;
    $d = sp_service()->removeEvidence($d, $owner);
    expect($d->evidence_path)->toBeNull();
    Storage::disk('local')->assertMissing($path);
});

it('rejects evidence with a wrong type or size', function () {
    Storage::fake('local');

    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $d = sp_deliverable(sp_sponsor($event, $owner), $owner);

    expect(fn () => sp_service()->attachEvidence($d, $owner, UploadedFile::fake()->create('run.exe', 10)))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => sp_service()->attachEvidence($d, $owner, UploadedFile::fake()->create('big.pdf', 6000, 'application/pdf')))
        ->toThrow(InvalidArgumentException::class);
});

// --------------------------------------------------------------- task link

it('creates a day-of-event task for a deliverable and links it', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $d = sp_deliverable(sp_sponsor($event, $owner, ['name' => 'همراه']), $owner, ['due_at' => '2026-12-10']);

    $task = sp_service()->createDeliverableTask($d, $owner);

    expect($task->module)->toBe(TaskModule::Sponsorship)
        ->and($task->phase)->toBe(TaskPhase::During)
        ->and($task->event_id)->toBe($event->id)
        ->and($task->title)->toContain('همراه')
        ->and($d->fresh()->task_id)->toBe($task->id);

    // یک وظیفه برای هر تعهد
    expect(fn () => sp_service()->createDeliverableTask($d->fresh(), $owner))
        ->toThrow(InvalidArgumentException::class);

    sp_service()->unlinkTask($d->fresh(), $owner);
    expect($d->fresh()->task_id)->toBeNull();
});

it('links only live tasks of the same event', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $other = tk_event($owner);
    $d = sp_deliverable(sp_sponsor($event, $owner), $owner);

    $foreign = tk_task($other, $owner);
    expect(fn () => sp_service()->linkTask($d, $owner, $foreign))->toThrow(InvalidArgumentException::class);

    $own = tk_task($event, $owner);
    sp_service()->linkTask($d, $owner, $own);
    expect($d->fresh()->task_id)->toBe($own->id);

    // وظیفه‌ی حذف‌شده پیوند را آزاد می‌کند
    $own->delete();
    expect($d->fresh()->task)->toBeNull();

    $second = tk_task($event, $owner);
    sp_service()->linkTask($d->fresh(), $owner, $second);
    expect($d->fresh()->task_id)->toBe($second->id);

    $trashed = tk_task($event, $owner);
    $trashed->delete();
    sp_service()->unlinkTask($d->fresh(), $owner);
    expect(fn () => sp_service()->linkTask($d->fresh(), $owner, Task::withTrashed()->find($trashed->id)))
        ->toThrow(InvalidArgumentException::class);
});
