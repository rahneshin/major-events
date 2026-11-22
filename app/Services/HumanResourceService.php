<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\CommitteeRole;
use App\Enums\EventRole;
use App\Models\AccountUpgrade;
use App\Models\Committee;
use App\Models\Event;
use App\Models\EventInvitation;
use App\Models\User;
use App\Services\Sms\SmsGateway;
use App\Support\LogsActivity;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * «منابع انسانی» رویداد.
 *
 * جریان دعوت:
 *   enroll()  ← دعوت با موبایل (+ کمیته/پست) ← پیامکِ لینک /invite/{token}
 *   accept()  ← با کلیک روی لینک و ورود (OTP): عضو رویداد + کمیته/پست + نوع حساب پیش‌فرض
 *
 * کسی که از قبل عضو رویداد است لینک نمی‌خواهد؛ کمیته/پستش مستقیم ثبت می‌شود.
 * منطق عضویت در EventMemberService و CommitteeService می‌ماند.
 */
class HumanResourceService
{
    use LogsActivity;

    public const ENROLLS_PER_HOUR = 30;

    public const INVITE_DAYS = 7;

    /**
     * «رئیس کمیته» انتخابی نیست؛ از روی ریاست کمیته مشتق می‌شود (Event::roleOf).
     *
     * @var list<EventRole>
     */
    public const ASSIGNABLE_ROLES = [EventRole::EventManager, EventRole::Member, EventRole::Viewer];

    public function __construct(
        private EventMemberService $eventMembers,
        private CommitteeService $committees,
        private EntitlementService $entitlement,
        private SmsGateway $sms,
    ) {}

    /**
     * @return array{status: 'invited'|'assigned'|'unchanged', invitation: ?EventInvitation, user: ?User, sms_sent: bool}
     *
     * @throws \App\Exceptions\LimitExceededException
     * @throws InvalidArgumentException
     */
    public function enroll(
        Event $event,
        User $actor,
        string $phone,
        ?string $name = null,
        EventRole $role = EventRole::Member,
        ?Committee $committee = null,
        ?string $post = null,
        bool $head = false,
    ): array {
        $this->throttle($actor);

        $normalized = PhoneNumber::normalize($phone);
        if (! $normalized) {
            throw new InvalidArgumentException(__('auth.invalid_phone'));
        }

        if (! in_array($role, self::ASSIGNABLE_ROLES, true)) {
            throw new InvalidArgumentException(__('hr.role_not_assignable'));
        }

        if ($committee !== null) {
            if ((int) $committee->event_id !== (int) $event->id) {
                throw new InvalidArgumentException(__('hr.committee_not_in_event'));
            }
            if ($role === EventRole::Viewer) {
                throw new InvalidArgumentException(__('hr.viewer_cannot_hold_post'));
            }
        }

        $user = User::withTrashed()->where('phone', $normalized)->first();
        if ($user && ($user->trashed() || ! $user->isActive())) {
            throw new InvalidArgumentException(__('hr.user_unavailable'));
        }

        // از قبل عضو رویداد: مستقیم کمیته/پست
        if ($user && $event->hasMember($user)) {
            return $this->assignExistingMember($event, $actor, $user, $committee, $post, $head);
        }

        $this->entitlement->assertWithinLimit($actor, 'members_per_event', $this->eventMembers->memberCount($event));

        $invitation = $this->issueInvitation($event, $actor, $normalized, [
            'role' => $role,
            'committee_id' => $committee?->id,
            'post' => $this->cleanText($post),
            'is_head' => $head && $committee !== null,
            'invitee_name' => $this->cleanText($name),
        ]);

        self::logActivity('hr.invited', $invitation, $event->id, [
            'phone' => $normalized,
            'committee_id' => $committee?->id,
        ]);

        return [
            'status' => 'invited',
            'invitation' => $invitation,
            'user' => $user,
            'sms_sent' => $this->sendInviteSms($invitation),
        ];
    }

    /**
     * ارسال مجدد: توکن جدید (توکن خام ذخیره نمی‌شود) و تمدید ۷ روزه.
     */
    public function resend(EventInvitation $invitation, User $actor): bool
    {
        if (! in_array($invitation->status, ['pending', 'expired'], true)) {
            throw new InvalidArgumentException(__('hr.invite_invalid'));
        }

        $this->throttle($actor);

        $this->rotate($invitation, $actor);

        return $this->sendInviteSms($invitation);
    }

    public function revoke(EventInvitation $invitation): void
    {
        if ($invitation->status !== 'pending') {
            throw new InvalidArgumentException(__('hr.invite_invalid'));
        }

        $invitation->update(['status' => 'cancelled']);
        self::logActivity('hr.invite_revoked', $invitation, $invitation->event_id);
    }

    /**
     * پذیرش دعوت توسط کاربرِ واردشده.
     *
     * @throws InvalidArgumentException
     * @throws \App\Exceptions\LimitExceededException
     */
    public function accept(EventInvitation $invitation, User $user): Event
    {
        if (! $invitation->isPending()) {
            throw new InvalidArgumentException(__('hr.invite_invalid'));
        }

        if ($user->phone !== $invitation->phone) {
            throw new InvalidArgumentException(__('hr.invite_phone_mismatch'));
        }

        return DB::transaction(function () use ($invitation, $user) {
            // دوبار کلیک هم‌زمان نباید دوبار پردازش شود
            $fresh = EventInvitation::query()->whereKey($invitation->id)->lockForUpdate()->first();
            if ($fresh === null || ! $fresh->isPending()) {
                throw new InvalidArgumentException(__('hr.invite_invalid'));
            }

            $event = $fresh->event;
            $inviter = $fresh->inviter ?? $event->creator;

            if (blank($user->name) && filled($fresh->invitee_name)) {
                $user->forceFill(['name' => $fresh->invitee_name])->save();
            }

            // دعوت‌شده به «انتخاب نوع حساب» نمی‌رسد؛ حساب عادی؛ بعداً از «حساب من» قابل ارتقاست
            if ($user->account_type === null) {
                $user->forceFill(['account_type' => AccountType::Individual])->save();

                AccountUpgrade::query()->create([
                    'user_id' => $user->id,
                    'from_type' => null,
                    'to_type' => AccountType::Individual,
                    'reason' => 'event_invitation',
                    'performed_by' => $user->id,
                ]);
            }

            if (! $event->hasMember($user)) {
                // سقف عضو رویداد مربوط به دعوت‌کننده/صاحب رویداد است، نه کاربر جدید
                $this->eventMembers->addMember($event, $inviter, $user, $fresh->role);
            }

            $committee = $fresh->committee_id ? Committee::query()->where('event_id', $event->id)->find($fresh->committee_id) : null;
            if ($committee !== null && ! $committee->hasMember($user)) {
                $this->committees->addMember(
                    $committee,
                    $inviter,
                    $user,
                    $fresh->is_head ? CommitteeRole::Head : CommitteeRole::Member,
                    false,
                    $fresh->post,
                );
            }

            $fresh->update(['status' => 'accepted', 'accepted_at' => now()]);
            self::logActivity('hr.invite_accepted', $fresh, $event->id, ['user_id' => $user->id]);

            return $event;
        });
    }

    /**
     * عضو فعلی رویداد را به کمیته‌ی دیگری اضافه می‌کند (همراه پست).
     */
    public function assignToCommittee(
        Committee $committee,
        User $actor,
        User $user,
        ?string $post = null,
        bool $head = false,
    ): bool {
        $event = $committee->event;

        if (! $event->hasMember($user)) {
            throw new InvalidArgumentException(__('committee.must_be_event_member'));
        }

        if ($event->roleOf($user) === EventRole::Viewer) {
            throw new InvalidArgumentException(__('hr.viewer_cannot_hold_post'));
        }

        $this->committees->addMember(
            $committee,
            $actor,
            $user,
            $head ? CommitteeRole::Head : CommitteeRole::Member,
            false,
            $post,
        );

        return $this->sendAssignedSms($event, $user, $committee, $post);
    }

    /**
     * @return array{status: 'assigned'|'unchanged', invitation: null, user: User, sms_sent: bool}
     */
    private function assignExistingMember(Event $event, User $actor, User $user, ?Committee $committee, ?string $post, bool $head): array
    {
        $smsSent = false;
        $status = 'unchanged';

        if ($committee !== null) {
            if ($committee->hasMember($user)) {
                $this->committees->setPost($committee, $user, $post);
                if ($head) {
                    $this->committees->setHead($committee, $user);
                }
                $status = 'assigned';
            } else {
                $smsSent = $this->assignToCommittee($committee, $actor, $user, $post, $head);
                $status = 'assigned';
            }
        }

        return ['status' => $status, 'invitation' => null, 'user' => $user, 'sms_sent' => $smsSent];
    }

    /**
     * یک دعوت pending برای هر (رویداد، شماره)؛ دعوت مجدد همان را به‌روز و توکن را عوض می‌کند.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function issueInvitation(Event $event, User $actor, string $phone, array $attributes): EventInvitation
    {
        $invitation = EventInvitation::query()
            ->where('event_id', $event->id)
            ->where('phone', $phone)
            ->where('status', 'pending')
            ->first();

        if ($invitation === null) {
            $invitation = new EventInvitation(['event_id' => $event->id, 'phone' => $phone]);
        }

        $invitation->fill($attributes);
        $this->rotate($invitation, $actor);

        return $invitation;
    }

    private function rotate(EventInvitation $invitation, User $actor): void
    {
        $plain = Str::random(40);

        $invitation->fill([
            'token_hash' => EventInvitation::hashToken($plain),
            'invited_by' => $actor->id,
            'expires_at' => now()->addDays(self::INVITE_DAYS),
            'status' => 'pending',
        ])->save();

        $invitation->plainToken = $plain;
    }

    private function cleanText(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, 120);
    }

    private function throttle(User $actor): void
    {
        $key = 'event-enroll:'.$actor->id;

        if (RateLimiter::tooManyAttempts($key, self::ENROLLS_PER_HOUR)) {
            throw new InvalidArgumentException(__('hr.rate_limited'));
        }

        RateLimiter::hit($key, 3600);
    }

    private function assignmentText(?Committee $committee, ?string $post): string
    {
        if ($committee === null) {
            return '';
        }

        $text = __('hr.sms_assignment', ['committee' => $committee->name]);
        $post = trim((string) $post);

        return $post !== '' ? $text.__('hr.sms_post', ['post' => $post]) : $text.'. ';
    }

    /**
     * پیامک لینک دعوت. تا وصل‌شدن پنل پیامکی، درایور `log` فقط در لاگ می‌نویسد.
     * خطای ارسال هرگز دعوت را خراب نمی‌کند (ارسال مجدد از تب منابع انسانی ممکن است).
     */
    private function sendInviteSms(EventInvitation $invitation): bool
    {
        try {
            $invitation->loadMissing(['event', 'committee']);

            return $this->sms->send($invitation->phone, __('hr.sms_invite', [
                'event' => $invitation->event->title,
                'assignment' => $this->assignmentText($invitation->committee, $invitation->post),
                'url' => route('events.invitation', ['token' => $invitation->plainToken]),
            ]));
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    private function sendAssignedSms(Event $event, User $user, ?Committee $committee, ?string $post): bool
    {
        try {
            return $this->sms->send($user->phone, __('hr.sms_assigned', [
                'event' => $event->title,
                'assignment' => $this->assignmentText($committee, $post),
                'url' => route('auth.login'),
            ]));
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
