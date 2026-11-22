<?php

use App\Models\User;

// مسیرهایی که پشت auth + account.type هستند
$authRoutes = [
    'app.dashboard' => '/app',
    'account.index' => '/account',
    'orgs.index' => '/organizations',
    'events.index' => '/events',
    'events.create' => '/events/create',
    'tasks.mine' => '/tasks',
    'admin.dashboard' => '/admin',
    'system.dashboard' => '/system',
];

it('redirects guests to the login page on protected routes', function (string $name) {
    $this->get(route($name))->assertRedirect(route('auth.login'));
})->with(array_keys($authRoutes));

it('sends users without a name to complete-profile', function (string $name) {
    $user = User::factory()->withoutName()->create();

    $this->actingAs($user)
        ->get(route($name))
        ->assertRedirect(route('auth.complete-profile'));
})->with(array_keys($authRoutes));

it('sends users without an account type to select-account-type', function (string $name) {
    $user = User::factory()->withoutAccountType()->create();

    $this->actingAs($user)
        ->get(route($name))
        ->assertRedirect(route('auth.select-account-type'));
})->with(array_keys($authRoutes));

it('forbids admin and system panels for regular users', function (string $name) {
    foreach ([User::factory()->individual()->create(), User::factory()->organizational()->create()] as $user) {
        $this->actingAs($user)->get(route($name))->assertForbidden();
    }
})->with(['admin.dashboard', 'system.dashboard']);

it('allows super admins into admin and system panels', function (string $name) {
    $this->withoutVite();
    $admin = User::factory()->organizational()->superAdmin()->create();

    $this->actingAs($admin)->get(route($name))->assertOk();
})->with(['admin.dashboard', 'system.dashboard']);

it('forbids suspended super admins', function () {
    $admin = User::factory()->superAdmin()->create(['status' => 'suspended']);

    $this->actingAs($admin)->get(route('admin.dashboard'))->assertForbidden();
});

it('lets regular users open their own pages', function (string $name) {
    $this->withoutVite();
    $user = User::factory()->individual()->create();

    $this->actingAs($user)->get(route($name))->assertOk();
})->with(['app.dashboard', 'account.index', 'events.index', 'events.create']);

it('forbids individual users from creating organizations', function () {
    $user = User::factory()->individual()->create();

    $this->actingAs($user)->get(route('orgs.create'))->assertForbidden();
});

it('does not expose is_super_admin to mass assignment', function () {
    $user = User::query()->create([
        'name' => 'تست',
        'phone' => '+989129999999',
        'is_super_admin' => true,
    ]);

    expect($user->fresh()->isSuperAdmin())->toBeFalse();
});
