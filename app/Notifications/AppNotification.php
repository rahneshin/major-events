<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use App\Services\NotificationPreferenceService;
use Illuminate\Notifications\Notification;

/**
 * Base for every in-app notification (Step 13): channels come from the user's
 * preferences. SMS is opt-in per notification through smsEligible() so that
 * only important events cost money.
 */
abstract class AppNotification extends Notification
{
    /** One of config('notifications.categories'). */
    abstract public function category(): string;

    public function smsEligible(): bool
    {
        return false;
    }

    public function toSms(object $notifiable): ?string
    {
        return null;
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return ['database'];
        }

        return app(NotificationPreferenceService::class)
            ->channels($notifiable, $this->category(), $this->smsEligible());
    }
}
