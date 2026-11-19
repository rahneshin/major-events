<?php

use App\Enums\AccountType;
use App\Exceptions\LimitExceededException;
use App\Models\AccountUpgrade;
use App\Models\User;
use App\Services\EntitlementService;
use Livewire\Livewire;

it('gives individual plan limits from config', function () {
    $user = User::factory()->individual()->create();
    $svc = app(EntitlementService::class);

    expect($svc->limit($user, 'active_events'))->toBe(1)
        ->and($svc->limit($user, 'organizations'))->toBe(0)
        ->and($svc->limit($user, 'committees_per_event'))->toBe(3)
        ->and($svc->can($user, 'tasks_basic'))->toBeTrue()
        ->and($svc->can($user, 'export_word'))->toBeFalse();
});

it('gives organizational unlimited events and all features', function () {
    $user = User::factory()->organizational()->create();
    $svc = app(EntitlementService::class);

    expect($svc->limit($user, 'active_events'))->toBeNull()
        ->and($svc->limit($user, 'organizations'))->toBe(3)
        ->and($svc->can($user, 'export_word'))->toBeTrue()
        ->and($svc->can($user, 'anything'))->toBeTrue();
});

it('detects when limit is reached', function () {
    $user = User::factory()->individual()->create();
    $svc = app(EntitlementService::class);

    expect($svc->isWithinLimit($user, 'active_events', 0))->toBeTrue()
        ->and($svc->isWithinLimit($user, 'active_events', 1))->toBeFalse()
        ->and($svc->remaining($user, 'active_events', 0))->toBe(1)
        ->and($svc->remaining($user, 'active_events', 1))->toBe(0);
});

it('throws LimitExceededException when asserting over limit', function () {
    $user = User::factory()->individual()->create();
    $svc = app(EntitlementService::class);

    $svc->assertWithinLimit($user, 'active_events', 1);
})->throws(LimitExceededException::class);

it('allows unlimited when limit is null', function () {
    $user = User::factory()->organizational()->create();
    $svc = app(EntitlementService::class);

    expect($svc->isWithinLimit($user, 'active_events', 999))->toBeTrue();
    $svc->assertWithinLimit($user, 'active_events', 999);
});

it('redirects user without account type to onboarding', function () {
    $user = User::factory()->withoutAccountType()->create();

    $this->actingAs($user)
        ->get(route('account.index'))
        ->assertRedirect(route('auth.select-account-type'));
});

it('lets user select account type on onboarding', function () {
    $user = User::factory()->withoutAccountType()->create();

    Livewire::actingAs($user)
        ->test(\App\Livewire\Auth\SelectAccountType::class)
        ->call('select', 'individual')
        ->call('save')
        ->assertRedirect(route('home'));

    $user->refresh();

    expect($user->account_type)->toBe(AccountType::Individual)
        ->and(AccountUpgrade::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('upgrades individual to organizational from account page', function () {
    $user = User::factory()->individual()->create();

    Livewire::actingAs($user)
        ->test(\App\Livewire\Account\AccountPage::class)
        ->call('upgrade');

    $user->refresh();

    expect($user->account_type)->toBe(AccountType::Organizational)
        ->and(AccountUpgrade::query()->where('user_id', $user->id)->where('reason', 'self_upgrade')->exists())->toBeTrue();
});

it('feature helper respects plan', function () {
    $individual = User::factory()->individual()->create();
    $org = User::factory()->organizational()->create();

    $this->actingAs($individual);
    expect(feature('export_pdf'))->toBeTrue()
        ->and(feature('export_word'))->toBeFalse();

    $this->actingAs($org);
    expect(feature('export_word'))->toBeTrue();
});

it('respects config changes without code changes', function () {
    config(['plans.individual.limits.active_events' => 5]);

    $user = User::factory()->individual()->create();
    $svc = app(EntitlementService::class);

    expect($svc->limit($user, 'active_events'))->toBe(5);
});
