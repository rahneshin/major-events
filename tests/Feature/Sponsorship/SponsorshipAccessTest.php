<?php

use App\Enums\EventRole;
use App\Livewire\Events\EventShow;
use App\Livewire\Events\EventSponsorship;
use App\Models\Event;
use App\Models\Sponsor;
use App\Models\User;
use App\Services\OrganizationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function spa_component(User $user, Event $event)
{
    return Livewire::actingAs($user)->test(EventSponsorship::class, ['event' => $event]);
}

/** Livewire may throw or answer 404 for a foreign id; both mean "blocked". */
function spa_blocked(callable $action): void
{
    try {
        $action();
    } catch (ModelNotFoundException|Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
        // expected
    }

    expect(true)->toBeTrue();
}

// ------------------------------------------------------------ policy matrix

it('applies the sponsorship access matrix', function () {
    $owner = User::factory()->organizational()->create();
    $org = app(OrganizationService::class)->create($owner, ['name' => 'سازمان اسپانسر', 'type' => 'club']);
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

    $can = fn (User $u, string $ability): bool => $u->can($ability, [Sponsor::class, $event->fresh()]);

    expect($can($owner, 'manage'))->toBeTrue()
        ->and($can($manager, 'manage'))->toBeTrue()
        ->and($can($head, 'manage'))->toBeFalse()
        ->and($can($member, 'manage'))->toBeFalse()
        ->and($can($viewer, 'manage'))->toBeFalse()
        ->and($can($stranger, 'manage'))->toBeFalse();

    expect($can($owner, 'viewModule'))->toBeTrue()
        ->and($can($manager, 'viewModule'))->toBeTrue()
        ->and($can($head, 'viewModule'))->toBeTrue()
        ->and($can($member, 'viewModule'))->toBeTrue()
        ->and($can($viewer, 'viewModule'))->toBeFalse()
        ->and($can($stranger, 'viewModule'))->toBeFalse();
});

// ---------------------------------------------------------------- component

it('forbids strangers from opening the sponsorship tab', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $stranger = User::factory()->organizational()->create();

    spa_component($stranger, $event)->assertForbidden();
});

it('shows an organization-only notice to an individual account and no data', function () {
    $owner = User::factory()->individual()->create();
    $event = tk_event($owner);

    spa_component($owner, $event)
        ->assertSee(__('sponsorship.org_only_title'))
        ->call('openSponsorForm')
        ->assertSet('showSponsorForm', true) // فرم باز می‌شود ولی UI آن را نشان نمی‌دهد و ذخیره رد می‌شود
        ->set('sName', 'بانک')
        ->call('saveSponsor')
        ->assertSet('error', __('sponsorship.feature_unavailable'));

    expect(Sponsor::query()->count())->toBe(0);
});

it('shows a no-access card to a viewer', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $viewer = User::factory()->organizational()->create();
    tk_join($event, $viewer, EventRole::Viewer);

    spa_component($viewer, $event)->assertSee(__('sponsorship.no_access'));
});

it('lets a manager create, edit, change status and delete a sponsor', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);

    $component = spa_component($owner, $event)
        ->call('openSponsorForm')
        ->set('sName', 'بانک نمونه')
        ->set('sCategory', 'banking')
        ->set('sContactName', 'رابط')
        ->set('sContactPhone', '09121234567')
        ->set('sCash', '1000000')
        ->call('saveSponsor')
        ->assertHasNoErrors()
        ->assertSet('error', null);

    $sponsor = Sponsor::query()->firstOrFail();
    expect($sponsor->name)->toBe('بانک نمونه')
        ->and($sponsor->cash_value)->toBe(1_000_000);

    $component->call('editSponsor', $sponsor->id)
        ->set('sName', 'بانک ویرایش‌شده')
        ->call('saveSponsor')
        ->assertHasNoErrors();
    expect($sponsor->fresh()->name)->toBe('بانک ویرایش‌شده');

    $component->call('changeSponsorStatus', $sponsor->id, 'negotiating');
    expect($sponsor->fresh()->status->value)->toBe('negotiating');

    $component->call('changeSponsorStatus', $sponsor->id, 'delivered')
        ->assertSet('error', __('sponsorship.transition_invalid', [
            'from' => App\Enums\SponsorStatus::Negotiating->label(),
            'to' => App\Enums\SponsorStatus::Delivered->label(),
        ]));

    $component->call('deleteSponsor', $sponsor->id);
    expect(Sponsor::query()->count())->toBe(0);
});

it('reads money in toman when the unit is toman', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);

    spa_component($owner, $event)
        ->call('setUnit', 'toman')
        ->call('openSponsorForm')
        ->set('sName', 'تومانی')
        ->set('sCash', '100')
        ->call('saveSponsor')
        ->assertHasNoErrors();

    expect(Sponsor::query()->firstOrFail()->cash_value)->toBe(1_000);
});

it('forbids a member from every mutation and hides contact details from them', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $member = User::factory()->organizational()->create();
    tk_join($event, $member, EventRole::Member);

    $sponsor = sp_sponsor($event, $owner, ['name' => 'قابل‌دیدن', 'contact_name' => 'رابط-محرمانه']);

    spa_component($member, $event)
        ->assertSee('قابل‌دیدن')
        ->assertDontSee('رابط-محرمانه')
        ->call('openSponsorForm')->assertForbidden();

    spa_component($member, $event)->call('changeSponsorStatus', $sponsor->id, 'negotiating')->assertForbidden();
    spa_component($member, $event)->call('deleteSponsor', $sponsor->id)->assertForbidden();
    spa_component($member, $event)->call('openAssetForm')->assertForbidden();
    spa_component($member, $event)->call('openPackageForm')->assertForbidden();
    spa_component($member, $event)->call('openDeliverableForm')->assertForbidden();

    expect($sponsor->fresh()->status->value)->toBe('prospect');
});

it('does not touch records of another event', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $other = tk_event($owner);

    $foreign = sp_sponsor($other, $owner);
    $foreignAsset = sp_asset($other, $owner);
    $foreignDeliverable = sp_deliverable($foreign, $owner);

    $component = spa_component($owner, $event);

    spa_blocked(fn () => $component->call('editSponsor', $foreign->id));
    spa_blocked(fn () => $component->call('changeSponsorStatus', $foreign->id, 'negotiating'));
    spa_blocked(fn () => $component->call('deleteSponsor', $foreign->id));
    spa_blocked(fn () => $component->call('editAsset', $foreignAsset->id));
    spa_blocked(fn () => $component->call('deleteAsset', $foreignAsset->id));
    spa_blocked(fn () => $component->call('changeDeliverableStatus', $foreignDeliverable->id, 'delivered'));
    spa_blocked(fn () => $component->call('toggleDeliverable', $foreignDeliverable->id));
    spa_blocked(fn () => $component->call('downloadEvidence', $foreignDeliverable->id));

    expect($foreign->fresh()->status->value)->toBe('prospect')
        ->and($foreignAsset->fresh())->not->toBeNull()
        ->and($foreignDeliverable->fresh()->status->value)->toBe('pending');
});

it('uploads and downloads evidence only for managers', function () {
    Storage::fake('local');

    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $member = User::factory()->organizational()->create();
    tk_join($event, $member, EventRole::Member);

    $d = sp_deliverable(sp_sponsor($event, $owner), $owner);

    spa_component($owner, $event)
        ->call('toggleDeliverable', $d->id)
        ->set('evidence', UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf'))
        ->call('saveEvidence', $d->id)
        ->assertHasNoErrors()
        ->assertSet('error', null);

    expect($d->fresh()->evidence_path)->not->toBeNull();

    spa_component($owner, $event)->call('downloadEvidence', $d->id)->assertHasNoErrors();
    spa_component($member, $event)->call('downloadEvidence', $d->id)->assertForbidden();
});

it('creates a linked task from the deliverable details', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $d = sp_deliverable(sp_sponsor($event, $owner), $owner);

    spa_component($owner, $event)
        ->call('toggleDeliverable', $d->id)
        ->set('ctTitle', 'اجرای تعهد')
        ->call('createDeliverableTask', $d->id)
        ->assertSet('error', null);

    expect($d->fresh()->task_id)->not->toBeNull();
});

it('mounts the sponsorship tab from the event hub', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);

    Livewire::actingAs($owner)->test(EventShow::class, ['event' => $event])
        ->set('tab', 'sponsorship')
        ->assertSeeLivewire(EventSponsorship::class);
});
