<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CommitteeRole;
use App\Enums\EventRole;
use App\Models\Committee;
use App\Models\Event;
use App\Models\User;
use App\Services\Sms\SmsGateway;
use App\Support\LogsActivity;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;
use Throwable;

/**
 * «منابع انسانی» رویداد: یک نقطه‌ی ورود برای
 *   ثبت‌نام فرد (اگر کاربر نبود) ← عضو رویداد ← عضو کمیته با «پست» ← پیامک خبر.
 *
 * منطق عضویت همچنان در EventMemberService و CommitteeService است؛ این سرویس فقط
 * آن‌ها را به‌هم وصل می‌کند و تکرار قانون ایجاد نمی‌کند.
 */
class HumanResourceService
{
    use LogsActivity;

    public const ENROLLS_PER_HOUR = 30;

    /**
     * نقش‌های قابل‌انتخاب هنگام ثبت. «رئیس کمیته» انتخابی نیست چون از روی
     * رئیس‌بودن در کمیته مشتق می‌شود (Event::roleOf).
     *
     * @var list<EventRole>
     */
    public const ASSIGNABLE_ROLES = [EventRole::EventManager, EventRole::Member, EventRole::Viewer];

    public function __construct(
        private EventMemberService $eventMembers,
        private CommitteeService $committees,
        private SmsGateway $sms,
    ) {}

    /**
     * ثبت یک نفر با شماره موبایل. اگر کاربر وجود نداشت ساخته می‌شود؛ اگر عضو رویداد
     * نبود عضو می‌شود؛ اگر کمیته داده شده باشد با «پست» به آن اضافه می‌شود.
     * هر مرحله idempotent است (تکراری‌بودن خطا نیست).
     *
     * @return array{user: User, created: bool, joined_event: bool, joined_committee: bool, sms_sent: bool}
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
            $this->assertCommitteeOfEvent($event, $committee);

            if ($role === EventRole::Viewer) {
                throw new InvalidArgumentException(__('hr.viewer_cannot_hold_post'));
            }
        }

        $name = $this->cleanName($name);

        $user = User::withTrashed()->where('phone', $normalized)->first();

        if ($user?->trashed() || ($user && ! $user->isActive())) {
            throw new InvalidArgumentException(__('hr.user_unavailable'));
        }

        $created = false;
        $joinedEvent = false;
        $joinedCommittee = false;

        DB::transaction(function () use (
            $event, $actor, $normalized, $name, $role, $committee, $post, $head,
            &$user, &$created, &$joinedEvent, &$joinedCommittee,
        ) {
            if ($user === null) {
                $user = User::query()->create([
                    'phone' => $normalized,
                    'name' => $name,
                    'status' => 'active',
                ]);
                $created = true;
            } elseif (blank($user->name) && $name !== null) {
                $user->forceFill(['name' => $name])->save();
            }

            if (! $event->hasMember($user)) {
                $this->eventMembers->addMember($event, $actor, $user, $role);
                $joinedEvent = true;
            }

            if ($committee !== null) {
                if ($committee->hasMember($user)) {
                    $this->committees->setPost($committee, $user, $post);
                    if ($head) {
                        $this->committees->setHead($committee, $user);
                    }
                } else {
                    $this->committees->addMember(
                        $committee,
                        $actor,
                        $user,
                        $head ? CommitteeRole::Head : CommitteeRole::Member,
                        false,
                        $post,
                    );
                    $joinedCommittee = true;
                }
            }

            if ($created) {
                self::logActivity('hr.user_registered', $user, $event->id, ['phone' => $normalized]);
            }
        });

        $smsSent = false;
        if ($joinedEvent || $joinedCommittee) {
            $smsSent = $this->sendSms($event, $user, $committee, $post);
        }

        return [
            'user' => $user,
            'created' => $created,
            'joined_event' => $joinedEvent,
            'joined_committee' => $joinedCommittee,
            'sms_sent' => $smsSent,
        ];
    }

    /**
     * افزودن عضو فعلی رویداد به یک کمیته‌ی دیگر (با پست).
     *
     * @throws InvalidArgumentException
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

        return $this->sendSms($event, $user, $committee, $post);
    }

    private function assertCommitteeOfEvent(Event $event, Committee $committee): void
    {
        if ((int) $committee->event_id !== (int) $event->id) {
            throw new InvalidArgumentException(__('hr.committee_not_in_event'));
        }
    }

    private function cleanName(?string $name): ?string
    {
        $name = trim((string) $name);

        return $name === '' ? null : mb_substr($name, 0, 120);
    }

    private function throttle(User $actor): void
    {
        $key = 'event-enroll:'.$actor->id;

        if (RateLimiter::tooManyAttempts($key, self::ENROLLS_PER_HOUR)) {
            throw new InvalidArgumentException(__('hr.rate_limited'));
        }

        RateLimiter::hit($key, 3600);
    }

    /**
     * پیامک خبر. تا زمان وصل‌شدن پنل پیامکی، درایور `log` فقط در لاگ می‌نویسد.
     * خطای ارسال هرگز ثبت‌نام را خراب نمی‌کند.
     */
    private function sendSms(Event $event, User $user, ?Committee $committee, ?string $post): bool
    {
        try {
            $assignment = '';
            if ($committee !== null) {
                $assignment = __('hr.sms_assignment', ['committee' => $committee->name]);
                $post = trim((string) $post);
                if ($post !== '') {
                    $assignment .= __('hr.sms_post', ['post' => $post]);
                }
            }

            return $this->sms->send($user->phone, __('hr.sms_enrolled', [
                'event' => $event->title,
                'assignment' => $assignment,
                'url' => route('auth.login'),
            ]));
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
