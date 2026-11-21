<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EventRole;
use App\Models\Event;
use App\Models\EventInvitation;
use App\Models\User;
use App\Support\LogsActivity;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use InvalidArgumentException;

class EventMemberService
{
    use LogsActivity;

    public const INVITES_PER_HOUR = 20;

    public function __construct(
        private EntitlementService $entitlement,
    ) {}

    public function memberCount(Event $event): int
    {
        return $event->members()->count();
    }

    public function addMember(Event $event, User $actor, User $member, EventRole $role = EventRole::Member): void
    {
        if ($event->hasMember($member)) {
            throw new InvalidArgumentException(__('event.member_already'));
        }

        $this->entitlement->assertWithinLimit(
            $actor,
            'members_per_event',
            $this->memberCount($event),
        );

        $event->members()->attach($member->id, [
            'role' => $role->value,
            'added_by' => $actor->id,
            'joined_at' => now(),
        ]);

        self::logActivity('event.member_added', $member, $event->id, [
            'role' => $role->value,
            'member_id' => $member->id,
        ]);
    }

    public function changeRole(Event $event, User $actor, User $member, EventRole $role): void
    {
        if (! $event->hasMember($member)) {
            throw new InvalidArgumentException(__('event.member_not_found'));
        }

        $current = $event->roleOf($member);
        if ($current === EventRole::EventOwner && $role !== EventRole::EventOwner) {
            throw new InvalidArgumentException(__('event.cannot_demote_owner'));
        }

        $event->members()->updateExistingPivot($member->id, ['role' => $role->value]);

        self::logActivity('event.member_role_changed', $member, $event->id, [
            'from' => $current?->value,
            'to' => $role->value,
        ]);
    }

    public function removeMember(Event $event, User $actor, User $member): void
    {
        $role = $event->roleOf($member);
        if ($role === EventRole::EventOwner) {
            throw new InvalidArgumentException(__('event.cannot_remove_owner'));
        }

        DB::transaction(function () use ($event, $member) {
            // A removed event member must leave all committees of this event.
            $committeeIds = $event->committees()->pluck('id');
            DB::table('committee_user')
                ->whereIn('committee_id', $committeeIds)
                ->where('user_id', $member->id)
                ->delete();
            $event->committees()->where('head_user_id', $member->id)->update(['head_user_id' => null]);

            $event->members()->detach($member->id);
        });

        self::logActivity('event.member_removed', $member, $event->id, [
            'role' => $role?->value,
        ]);
    }

    public function inviteByPhone(Event $event, User $actor, string $phone, EventRole $role = EventRole::Member): EventInvitation
    {
        $this->throttleInvites($actor);

        $normalized = PhoneNumber::normalize($phone);
        if (! $normalized) {
            throw new InvalidArgumentException(__('auth.invalid_phone'));
        }

        $this->entitlement->assertWithinLimit(
            $actor,
            'members_per_event',
            $this->memberCount($event),
        );

        // If user already exists and not a member, add directly
        $existing = User::query()->where('phone', $normalized)->first();
        if ($existing && ! $event->hasMember($existing)) {
            $this->addMember($event, $actor, $existing, $role);

            return $this->createInvitation($event, $actor, $normalized, $role, 'accepted', now());
        }

        if ($existing && $event->hasMember($existing)) {
            throw new InvalidArgumentException(__('event.member_already'));
        }

        $invitation = $this->createInvitation($event, $actor, $normalized, $role, 'pending');

        self::logActivity('event.member_invited', $invitation, $event->id, [
            'phone' => $normalized,
            'role' => $role->value,
        ]);

        return $invitation;
    }

    private function createInvitation(
        Event $event,
        User $actor,
        string $phone,
        EventRole $role,
        string $status,
        ?\DateTimeInterface $acceptedAt = null,
    ): EventInvitation {
        $plain = Str::random(40);

        $invitation = EventInvitation::query()->create([
            'event_id' => $event->id,
            'phone' => $phone,
            'role' => $role,
            'token_hash' => EventInvitation::hashToken($plain),
            'invited_by' => $actor->id,
            'expires_at' => now()->addDays(7),
            'accepted_at' => $acceptedAt,
            'status' => $status,
        ]);

        $invitation->plainToken = $plain;

        return $invitation;
    }

    /**
     * سقف دعوت برای هر کاربر (جلوگیری از اسپم پیامک و شمارش وجود/عدم وجود شماره).
     */
    private function throttleInvites(User $actor): void
    {
        $key = 'event-invite:'.$actor->id;

        if (RateLimiter::tooManyAttempts($key, self::INVITES_PER_HOUR)) {
            throw new InvalidArgumentException(__('event.invite_rate_limited'));
        }

        RateLimiter::hit($key, 3600);
    }

    public function acceptInvitation(EventInvitation $invitation, User $user): void
    {
        if (! $invitation->isPending()) {
            throw new InvalidArgumentException(__('event.invitation_invalid'));
        }

        if ($user->phone !== $invitation->phone) {
            throw new InvalidArgumentException(__('event.invitation_phone_mismatch'));
        }

        DB::transaction(function () use ($invitation, $user) {
            if (! $invitation->event->hasMember($user)) {
                $invitation->event->members()->attach($user->id, [
                    'role' => $invitation->role->value,
                    'added_by' => $invitation->invited_by,
                    'joined_at' => now(),
                ]);
            }

            $invitation->update([
                'status' => 'accepted',
                'accepted_at' => now(),
            ]);
        });
    }
}
