<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Event;
use App\Models\EventAnnouncement;
use App\Models\User;
use App\Notifications\EventAnnouncementPosted;
use App\Support\LogsActivity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;

/**
 * Step 13 — «اطلاعیه‌ی سریع» به همه‌ی اعضای رویداد (مرکز کنترل روز رویداد).
 *
 * Authorization is NOT done here (project rule): the Livewire component
 * authorizes `EventPolicy@update` first. The service guarantees data rules:
 * active event only, text length, rate limits and the SMS cost cap.
 */
class AnnouncementService
{
    use LogsActivity;

    public function publish(Event $event, User $author, string $body, bool $urgent = false): EventAnnouncement
    {
        if (! $event->isActive()) {
            throw new InvalidArgumentException(__('notification.event_closed'));
        }

        $body = trim((string) preg_replace('/\s+/u', ' ', $body));
        $length = mb_strlen($body);

        if ($length < (int) config('notifications.announcement.min_length', 3)
            || $length > (int) config('notifications.announcement.max_length', 500)) {
            throw new InvalidArgumentException(__('notification.announcement_invalid'));
        }

        $hourKey = 'announce:'.$author->id;
        if (RateLimiter::tooManyAttempts($hourKey, (int) config('notifications.announcement.per_user_per_hour', 20))) {
            throw new InvalidArgumentException(__('notification.announcement_rate_limited'));
        }

        $smsKey = 'announce-sms:'.$event->id.':'.now('Asia/Tehran')->toDateString();
        if ($urgent && RateLimiter::tooManyAttempts($smsKey, (int) config('notifications.sms.announcement_per_event_per_day', 3))) {
            throw new InvalidArgumentException(__('notification.sms_cap_reached'));
        }

        $recipients = $event->members()
            ->where('users.id', '!=', $author->id)
            ->get()
            ->filter(fn (User $user) => $user->isActive())
            ->values();

        $announcement = DB::transaction(function () use ($event, $author, $body, $urgent, $recipients) {
            $announcement = EventAnnouncement::query()->create([
                'event_id' => $event->id,
                'user_id' => $author->id,
                'body' => $body,
                'urgent' => $urgent,
                'recipients_count' => $recipients->count(),
            ]);

            self::logActivity('announcement.posted', $announcement, $event->id, [
                'urgent' => $urgent,
                'recipients' => $recipients->count(),
            ]);

            return $announcement;
        });

        RateLimiter::hit($hourKey, 3600);
        if ($urgent) {
            RateLimiter::hit($smsKey, 86400);
        }

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new EventAnnouncementPosted($announcement));
        }

        return $announcement;
    }
}
