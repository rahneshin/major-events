<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Jobs\SendSmsMessage;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\NotificationPreferenceService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Custom channel: hands the text to a queued job (SmsGateway behind it).
 * Never throws into the caller — a broken SMS must not break a task/announcement.
 */
class SmsChannel
{
    public function __construct(private NotificationPreferenceService $preferences) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof User || ! $notification instanceof AppNotification || blank($notifiable->phone)) {
            return;
        }

        $message = $notification->toSms($notifiable);

        if (blank($message)) {
            return;
        }

        if (! $this->preferences->consumeSmsQuota($notifiable)) {
            Log::info('SMS skipped: daily cap reached', [
                'user_id' => $notifiable->id,
                'category' => $notification->category(),
            ]);

            return;
        }

        try {
            SendSmsMessage::dispatch(
                $notifiable->phone,
                Str::limit((string) $message, (int) config('notifications.sms.max_length', 140), ''),
                $notifiable->id,
                $notification->category(),
            );
        } catch (Throwable $e) {
            report($e);
        }
    }
}
