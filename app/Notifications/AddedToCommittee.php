<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Committee;
use Illuminate\Notifications\Notification;

class AddedToCommittee extends Notification
{
    public function __construct(
        public Committee $committee,
        public string $role,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        // In-app only; SMS arrives with Step 13.
        return ['database'];
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
            'message' => __('committee.notify_added', [
                'committee' => $this->committee->name,
                'event' => $event->title,
            ]),
        ];
    }
}
