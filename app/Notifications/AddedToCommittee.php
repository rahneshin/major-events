<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Committee;

class AddedToCommittee extends AppNotification
{
    public function __construct(
        public Committee $committee,
        public string $role,
    ) {}

    public function category(): string
    {
        // In-app only (SMS for committee assignment is sent by HumanResourceService).
        return 'committee';
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $event = $this->committee->event;

        return [
            'type' => 'committee.added',
            'committee_id' => $this->committee->id,
            'committee_name' => $this->committee->name,
            'event_uuid' => $event->uuid,
            'event_title' => $event->title,
            'role' => $this->role,
            'url' => route('events.show', ['event' => $event->uuid, 'tab' => 'committees']),
            'message' => __('committee.notify_added', [
                'committee' => $this->committee->name,
                'event' => $event->title,
            ]),
        ];
    }
}
