<?php

use App\Models\OtpCode;
use App\Models\User;
use App\Services\OtpService;
use App\Services\Sms\NullSmsGateway;
use App\Services\Sms\SmsGateway;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    $this->app->bind(SmsGateway::class, NullSmsGateway::class);
    RateLimiter::clear('otp-phone:+989123456789');
    RateLimiter::clear('otp-phone-hourly:+989123456789');
    RateLimiter::clear('otp-ip:127.0.0.1');
});

it('sends otp code successfully', function () {
    $otp = app(OtpService::class);

    $otp->send('09123456789', '127.0.0.1', 'test-agent');

    $this->assertDatabaseHas('otp_codes', [
        'phone' => '+989123456789',
        'purpose' => 'login',
    ]);

    $record = OtpCode::query()->where('phone', '+989123456789')->latest('id')->first();
    expect($record)->not->toBeNull();
    expect($record->code_hash)->not->toBeEmpty();
    expect($record->expires_at->isFuture())->toBeTrue();
});

it('verifies correct code and logs user in', function () {
    $otp = app(OtpService::class);
    $code = '123456';

    OtpCode::query()->create([
        'phone' => '+989123456789',
        'code_hash' => Hash::make($code),
        'purpose' => 'login',
        'expires_at' => now()->addMinutes(2),
        'attempts' => 0,
    ]);

    $user = $otp->verify('09123456789', $code, '127.0.0.1');

    expect($user)->toBeInstanceOf(User::class);
    expect($user->phone)->toBe('+989123456789');
    expect(auth()->check())->toBeTrue();
    expect(auth()->id())->toBe($user->id);
});

it('rejects wrong code', function () {
    $otp = app(OtpService::class);

    OtpCode::query()->create([
        'phone' => '+989123456789',
        'code_hash' => Hash::make('123456'),
        'purpose' => 'login',
        'expires_at' => now()->addMinutes(2),
        'attempts' => 0,
    ]);

    $otp->verify('09123456789', '000000', '127.0.0.1');
})->throws(\Illuminate\Validation\ValidationException::class);

it('rejects expired code', function () {
    $otp = app(OtpService::class);

    OtpCode::query()->create([
        'phone' => '+989123456789',
        'code_hash' => Hash::make('123456'),
        'purpose' => 'login',
        'expires_at' => now()->subMinute(),
        'attempts' => 0,
    ]);

    $otp->verify('09123456789', '123456', '127.0.0.1');
})->throws(\Illuminate\Validation\ValidationException::class);

it('rejects after max attempts', function () {
    $otp = app(OtpService::class);

    OtpCode::query()->create([
        'phone' => '+989123456789',
        'code_hash' => Hash::make('123456'),
        'purpose' => 'login',
        'expires_at' => now()->addMinutes(2),
        'attempts' => 5,
    ]);

    $otp->verify('09123456789', '123456', '127.0.0.1');
})->throws(\Illuminate\Validation\ValidationException::class);

it('creates new user on first login', function () {
    $otp = app(OtpService::class);
    $code = '654321';

    OtpCode::query()->create([
        'phone' => '+989111111111',
        'code_hash' => Hash::make($code),
        'purpose' => 'login',
        'expires_at' => now()->addMinutes(2),
        'attempts' => 0,
    ]);

    $user = $otp->verify('09111111111', $code);

    expect($user->wasRecentlyCreated)->toBeTrue();
    expect($user->name)->toBeNull();
    expect($user->phone_verified_at)->not->toBeNull();
});

it('guest can view login page', function () {
    $this->get(route('auth.login'))->assertOk();
});

it('authenticated user can logout', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('auth.logout'))
        ->assertRedirect(route('auth.login'));

    $this->assertGuest();
});
