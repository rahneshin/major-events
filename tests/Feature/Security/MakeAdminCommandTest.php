<?php

use App\Models\User;

it('grants and revokes super admin by phone', function () {
    $user = User::factory()->create(['phone' => '+989121234567']);

    $this->artisan('user:make-admin 09121234567')->assertExitCode(0);
    expect($user->fresh()->isSuperAdmin())->toBeTrue();

    $this->artisan('user:make-admin ۰۹۱۲۱۲۳۴۵۶۷ --revoke')->assertExitCode(0);
    expect($user->fresh()->isSuperAdmin())->toBeFalse();
});

it('fails for an invalid phone', function () {
    $this->artisan('user:make-admin 123')->assertExitCode(1);
});

it('fails for an unknown user and does not create one', function () {
    $this->artisan('user:make-admin 09125550000')->assertExitCode(1);

    expect(User::query()->where('phone', '+989125550000')->exists())->toBeFalse();
});
