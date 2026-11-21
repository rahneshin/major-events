<?php

use App\Enums\EventRole;
use App\Exceptions\LimitExceededException;
use App\Models\EventInvitation;
use App\Models\User;
use App\Services\EventMemberService;
use App\Services\EventService;
use Illuminate\Support\Facades\Schema;

function mem_event(User $owner)
{
    return app(EventService::class)->create($owner, ['title' => 'رویداد اعضا']);
}

it('stores only a hashed invitation token', function () {
    $owner = User::factory()->organizational()->create();
    $event = mem_event($owner);

    $invitation = app(EventMemberService::class)->inviteByPhone($event, $owner, '09127770000', EventRole::Member);

    expect(Schema::hasColumn('event_invitations', 'token'))->toBeFalse()
        ->and($invitation->status)->toBe('pending')
        ->and($invitation->plainToken)->toHaveLength(40)
        ->and($invitation->fresh()->token_hash)->toBe(hash('sha256', $invitation->plainToken))
        ->and($invitation->fresh()->token_hash)->not->toBe($invitation->plainToken);
});

it('finds invitations by plain token and rejects wrong tokens', function () {
    $owner = User::factory()->organizational()->create();
    $event = mem_event($owner);
    $invitation = app(EventMemberService::class)->inviteByPhone($event, $owner, '09127770001', EventRole::Member);

    expect(EventInvitation::findByToken($invitation->plainToken)?->id)->toBe($invitation->id)
        ->and(EventInvitation::findByToken('wrong-token'))->toBeNull();
});

it('adds an existing user directly when invited by phone', function () {
    $owner = User::factory()->organizational()->create();
    $existing = User::factory()->create(['phone' => '+989127770002']);
    $event = mem_event($owner);

    $invitation = app(EventMemberService::class)->inviteByPhone($event, $owner, '09127770002', EventRole::Viewer);

    expect($invitation->status)->toBe('accepted')
        ->and($event->fresh()->roleOf($existing))->toBe(EventRole::Viewer);
});

it('rejects inviting someone who is already a member', function () {
    $owner = User::factory()->organizational()->create(['phone' => '+989127770003']);
    $event = mem_event($owner);

    app(EventMemberService::class)->inviteByPhone($event, $owner, '09127770003');
})->throws(InvalidArgumentException::class);

it('rejects an invalid phone number', function () {
    $owner = User::factory()->organizational()->create();
    $event = mem_event($owner);

    app(EventMemberService::class)->inviteByPhone($event, $owner, '123');
})->throws(InvalidArgumentException::class);

it('enforces members_per_event for individual accounts', function () {
    config(['plans.individual.limits.members_per_event' => 2]);

    $owner = User::factory()->individual()->create();
    $event = mem_event($owner);
    $svc = app(EventMemberService::class);

    $svc->addMember($event, $owner, User::factory()->create());
    $svc->addMember($event, $owner, User::factory()->create());
})->throws(LimitExceededException::class);

it('changes roles but never demotes or removes the owner', function () {
    $owner = User::factory()->organizational()->create();
    $member = User::factory()->create();
    $event = mem_event($owner);
    $svc = app(EventMemberService::class);

    $svc->addMember($event, $owner, $member);
    $svc->changeRole($event, $owner, $member, EventRole::EventManager);

    expect($event->fresh()->roleOf($member))->toBe(EventRole::EventManager);

    expect(fn () => $svc->changeRole($event, $owner, $owner, EventRole::Member))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => $svc->removeMember($event, $owner, $owner))
        ->toThrow(InvalidArgumentException::class);
});

it('removes a member', function () {
    $owner = User::factory()->organizational()->create();
    $member = User::factory()->create();
    $event = mem_event($owner);
    $svc = app(EventMemberService::class);

    $svc->addMember($event, $owner, $member);
    $svc->removeMember($event, $owner, $member);

    expect($event->fresh()->hasMember($member))->toBeFalse();
});

it('accepts a pending invitation only with the matching phone', function () {
    $owner = User::factory()->organizational()->create();
    $event = mem_event($owner);
    $svc = app(EventMemberService::class);

    $invitation = $svc->inviteByPhone($event, $owner, '09127770004', EventRole::Member);
    $wrong = User::factory()->create(['phone' => '+989127770999']);

    expect(fn () => $svc->acceptInvitation($invitation, $wrong))
        ->toThrow(InvalidArgumentException::class);

    $right = User::factory()->create(['phone' => '+989127770004']);
    $svc->acceptInvitation($invitation->fresh(), $right);

    expect($event->fresh()->hasMember($right))->toBeTrue()
        ->and($invitation->fresh()->status)->toBe('accepted');
});

it('rejects an expired invitation', function () {
    $owner = User::factory()->organizational()->create();
    $event = mem_event($owner);
    $svc = app(EventMemberService::class);

    $invitation = $svc->inviteByPhone($event, $owner, '09127770005');
    $invitation->update(['expires_at' => now()->subMinute()]);

    $user = User::factory()->create(['phone' => '+989127770005']);

    $svc->acceptInvitation($invitation->fresh(), $user);
})->throws(InvalidArgumentException::class);
