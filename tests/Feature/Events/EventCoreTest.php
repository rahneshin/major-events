<?php

use App\Enums\EventRole;
use App\Enums\EventStatus;
use App\Exceptions\LimitExceededException;
use App\Models\Event;
use App\Models\User;
use App\Services\EventService;

it('makes the creator the event owner', function () {
    $user = User::factory()->individual()->create();
    $event = app(EventService::class)->create($user, ['title' => 'مسابقه‌ی محلی']);

    expect($event->status)->toBe(EventStatus::Draft)
        ->and($event->roleOf($user))->toBe(EventRole::EventOwner)
        ->and($event->uuid)->not->toBeEmpty();
});

it('limits active events for individual accounts', function () {
    $user = User::factory()->individual()->create();
    $svc = app(EventService::class);

    $svc->create($user, ['title' => 'اول']);
    $svc->create($user, ['title' => 'دوم']);
})->throws(LimitExceededException::class);

it('does not limit active events for organizational accounts', function () {
    $user = User::factory()->organizational()->create();
    $svc = app(EventService::class);

    foreach (['الف', 'ب', 'ج', 'د'] as $title) {
        $svc->create($user, ['title' => $title]);
    }

    expect(Event::query()->where('created_by', $user->id)->count())->toBe(4);
});

it('frees the active-event slot after cancelling', function () {
    $user = User::factory()->individual()->create();
    $svc = app(EventService::class);

    $first = $svc->create($user, ['title' => 'اول']);
    $svc->transition($first, EventStatus::Cancelled);

    $second = $svc->create($user, ['title' => 'دوم']);

    expect($second->exists)->toBeTrue();
});

it('allows only valid status transitions', function () {
    $user = User::factory()->organizational()->create();
    $svc = app(EventService::class);
    $event = $svc->create($user, ['title' => 'گذار']);

    $svc->transition($event, EventStatus::Planning);

    expect($event->fresh()->status)->toBe(EventStatus::Planning);
});

it('rejects invalid status transitions', function () {
    $user = User::factory()->organizational()->create();
    $svc = app(EventService::class);
    $event = $svc->create($user, ['title' => 'گذار نامعتبر']);

    $svc->transition($event, EventStatus::Completed);
})->throws(InvalidArgumentException::class);

it('counts only active events in activeCountFor', function () {
    $user = User::factory()->organizational()->create();
    $svc = app(EventService::class);

    $a = $svc->create($user, ['title' => 'الف']);
    $svc->create($user, ['title' => 'ب']);
    $svc->transition($a, EventStatus::Cancelled);

    expect($svc->activeCountFor($user))->toBe(1);
});
