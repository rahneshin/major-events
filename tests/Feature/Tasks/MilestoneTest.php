<?php

use App\Enums\EventRole;
use App\Enums\MilestoneStatus;
use App\Models\Milestone;
use App\Models\User;
use App\Services\MilestoneService;

it('creates, updates and deletes milestones', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $svc = app(MilestoneService::class);

    $milestone = $svc->create($event, ['title' => '  پایان ثبت‌نام ', 'due_at' => '2026-12-15 12:00']);

    expect($milestone->title)->toBe('پایان ثبت‌نام')
        ->and($milestone->status)->toBe(MilestoneStatus::Pending)
        ->and($milestone->due_at->toDateString())->toBe('2026-12-15');

    $svc->update($milestone, ['title' => 'بستن ثبت‌نام', 'due_at' => null]);

    expect($milestone->fresh()->title)->toBe('بستن ثبت‌نام')
        ->and($milestone->fresh()->due_at)->toBeNull();

    $svc->delete($milestone);

    expect(Milestone::query()->count())->toBe(0);
});

it('validates milestone input', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $svc = app(MilestoneService::class);

    expect(fn () => $svc->create($event, ['title' => '  ']))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $svc->create($event, ['title' => 'x', 'due_at' => 'not a date']))->toThrow(InvalidArgumentException::class);
});

it('tracks reached_at and overdue state', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $svc = app(MilestoneService::class);

    $late = $svc->create($event, ['title' => 'گذشته', 'due_at' => now()->subDay()]);
    expect($late->isOverdue())->toBeTrue();

    $svc->setStatus($late, MilestoneStatus::Reached);
    expect($late->fresh()->reached_at)->not->toBeNull()
        ->and($late->fresh()->isOverdue())->toBeFalse();

    $svc->setStatus($late, MilestoneStatus::Pending);
    expect($late->fresh()->reached_at)->toBeNull();
});

it('orders milestones by due date with undated ones last', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $svc = app(MilestoneService::class);

    $undated = $svc->create($event, ['title' => 'بدون تاریخ']);
    $later = $svc->create($event, ['title' => 'دیرتر', 'due_at' => '2026-12-20']);
    $sooner = $svc->create($event, ['title' => 'زودتر', 'due_at' => '2026-12-01']);

    expect($event->milestones()->ordered()->pluck('id')->all())->toBe([$sooner->id, $later->id, $undated->id]);
});

it('lets members see milestones but only managers change them', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $manager = User::factory()->create();
    $member = User::factory()->create();
    $viewer = User::factory()->create();
    $stranger = User::factory()->organizational()->create();
    tk_join($event, $manager, EventRole::EventManager);
    tk_join($event, $member);
    tk_join($event, $viewer, EventRole::Viewer);

    $milestone = app(MilestoneService::class)->create($event, ['title' => 'افتتاحیه']);

    foreach ([$owner, $manager, $member, $viewer] as $user) {
        expect($user->can('view', $milestone))->toBeTrue()
            ->and($user->can('viewAny', [Milestone::class, $event]))->toBeTrue();
    }

    foreach ([$owner, $manager] as $user) {
        expect($user->can('create', [Milestone::class, $event]))->toBeTrue()
            ->and($user->can('update', $milestone))->toBeTrue()
            ->and($user->can('delete', $milestone))->toBeTrue();
    }

    foreach ([$member, $viewer, $stranger] as $user) {
        expect($user->can('create', [Milestone::class, $event]))->toBeFalse()
            ->and($user->can('update', $milestone))->toBeFalse()
            ->and($user->can('delete', $milestone))->toBeFalse();
    }

    expect($stranger->can('view', $milestone))->toBeFalse();
});
