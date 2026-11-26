<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\EventAnnouncement;

/**
 * اطلاعیه‌ی سریع مدیر رویداد؛ پیامک فقط وقتی «فوری» علامت خورده باشد.
 */
class EventAnnouncementPosted extends AppNotification
{
    public function __construct(public EventAnnouncement $announcement) {}

    public function category(): string
    {
        return 'announcement';
    }

    public function smsEligible(): bool
    {
        return $this->announcement->urgent;
    }

    public function toSms(object $notifiable): ?string
    {
        return __('notification.announcement_sms', [
            'event' => $this->announcement->event->title,
            'body' => $this->announcement->body,
        ]);
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $event = $this->announcement->event;

        return [
            'type' => 'event.announcement',
            'announcement_uuid' => $this->announcement->uuid,
            'event_uuid' => $event->uuid,
            'event_title' => $event->title,
            'urgent' => $this->announcement->urgent,
            'message' => __('notification.announcement_message', [
                'event' => $event->title,
                'body' => $this->announcement->body,
            ]),
            'url' => route('events.show', ['event' => $event->uuid, 'tab' => 'control']),
        ];
    }
}
