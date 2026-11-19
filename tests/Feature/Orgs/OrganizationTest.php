<?php

use App\Enums\AccountType;
use App\Enums\OrgRole;
use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationService;
use Livewire\Livewire;

it('prevents individual users from creating organizations', function () {
    $user = User::factory()->individual()->create();

    $this->actingAs($user)
        ->get(route('orgs.create'))
        ->assertForbidden();
});

it('allows organizational user to create organization within limit', function () {
    $user = User::factory()->organizational()->create();

    Livewire::actingAs($user)
        ->test(\App\Livewire\Orgs\OrganizationForm::class)
        ->set('name', 'باشگاه نمونه')
        ->set('type', 'club')
        ->set('city', 'تهران')
        ->call('save')
        ->assertHasNoErrors();

    expect(Organization::query()->where('owner_id', $user->id)->count())->toBe(1);

    $org = Organization::query()->first();
    expect($org->hasMember($user))->toBeTrue()
        ->and($org->roleOf($user))->toBe(OrgRole::Owner)
        ->and($user->fresh()->current_organization_id)->toBe($org->id);
});

it('enforces organizations limit for organizational plan', function () {
    config(['plans.organizational.limits.organizations' => 1]);

    $user = User::factory()->organizational()->create();
    $svc = app(OrganizationService::class);

    $svc->create($user, ['name' => 'Org 1', 'type' => 'club']);

    $svc->create($user, ['name' => 'Org 2', 'type' => 'club']);
})->throws(\App\Exceptions\LimitExceededException::class);

it('hides organization from non-members', function () {
    $owner = User::factory()->organizational()->create();
    $stranger = User::factory()->organizational()->create();

    $org = app(OrganizationService::class)->create($owner, [
        'name' => 'خصوصی',
        'type' => 'club',
    ]);

    $this->actingAs($stranger)
        ->get(route('orgs.show', $org))
        ->assertForbidden();
});

it('invites member by phone and rejects duplicate member', function () {
    $owner = User::factory()->organizational()->create();
    $member = User::factory()->organizational()->create(['phone' => '+989121111111']);

    $svc = app(OrganizationService::class);
    $org = $svc->create($owner, ['name' => 'باشگاه', 'type' => 'club']);

    $invite = $svc->invite($org, $owner, '09121111111', OrgRole::Member);
    expect($invite->isPending())->toBeTrue();

    $svc->acceptInvitation($invite, $member);
    expect($org->fresh()->hasMember($member))->toBeTrue();

    $svc->invite($org, $owner, '09121111111', OrgRole::Member);
})->throws(\Illuminate\Validation\ValidationException::class);

it('transfers ownership', function () {
    $owner = User::factory()->organizational()->create();
    $admin = User::factory()->organizational()->create();

    $svc = app(OrganizationService::class);
    $org = $svc->create($owner, ['name' => 'Org', 'type' => 'club']);

    $org->members()->attach($admin->id, [
        'role' => OrgRole::Admin->value,
        'joined_at' => now(),
    ]);

    $svc->transferOwnership($org, $admin, $owner);

    $org->refresh();
    expect($org->owner_id)->toBe($admin->id)
        ->and($org->roleOf($admin))->toBe(OrgRole::Owner)
        ->and($org->roleOf($owner))->toBe(OrgRole::Admin);
});
