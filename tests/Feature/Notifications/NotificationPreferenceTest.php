<?php

use App\Models\User;
use App\Notifications\Channels\SmsChannel;
use App\Services\NotificationPreferenceService;

it('uses in-app by default and adds SMS only for eligible notifications', function () {
    $user = User::factory()->create();
    $prefs = app(NotificationPreferenceService::class);

    expect($prefs->channels($user, 'task_assigned', false))->toBe(['database'])
        ->and($prefs->channels($user, 'task_assigned', true))->toBe(['database', SmsChannel::class]);
});

it('respects the user opting out of SMS or in-app', function () {
    $user = User::factory()->create();
    $prefs = app(NotificationPreferenceService::class);

    $prefs->set($user, 'task_due', true, false);
    expect($prefs->channels($user, 'task_due', true))->toBe(['database']);

    $prefs->set($user, 'task_due', false, false);
    expect($prefs->channels($user, 'task_due', true))->toBe([]);
});

it('never enables SMS for categories that do not support it', function () {
    $user = User::factory()->create();
    $prefs = app(NotificationPreferenceService::class);

    $prefs->set($user, 'committee', true, true);

    expect($prefs->forUser($user)['committee']['sms'])->toBeFalse()
        ->and($prefs->channels($user, 'committee', true))->toBe(['database']);
});

it('rejects an unknown category', function () {
    $user = User::factory()->create();

    expect(fn () => app(NotificationPreferenceService::class)->set($user, 'nope', true, true))
        ->toThrow(InvalidArgumentException::class);
});

it('caps SMS per user per day', function () {
    config(['notifications.sms.per_user_per_day' => 2]);
    $user = User::factory()->create();
    $prefs = app(NotificationPreferenceService::class);

    expect($prefs->consumeSmsQuota($user))->toBeTrue()
        ->and($prefs->consumeSmsQuota($user))->toBeTrue()
        ->and($prefs->consumeSmsQuota($user))->toBeFalse();

    // another user has an independent allowance
    expect($prefs->consumeSmsQuota(User::factory()->create()))->toBeTrue();
});
