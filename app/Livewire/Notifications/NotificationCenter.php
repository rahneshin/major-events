<?php

declare(strict_types=1);

namespace App\Livewire\Notifications;

use App\Services\NotificationPreferenceService;
use App\Support\NotificationLink;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Step 13 — صفحه‌ی اعلان‌ها (`/notifications`): فهرست، خوانده‌شدن و ترجیحات کانال‌ها.
 */
#[Layout('layouts.app')]
class NotificationCenter extends Component
{
    public bool $onlyUnread = false;

    public function markRead(string $id): void
    {
        Auth::user()->notifications()->whereKey($id)->first()?->markAsRead();
    }

    public function markAllRead(): void
    {
        Auth::user()->unreadNotifications()->update(['read_at' => now()]);
    }

    public function toggle(string $category, string $channel, NotificationPreferenceService $prefs): void
    {
        if (! $prefs->exists($category) || ! in_array($channel, ['in_app', 'sms'], true)) {
            return;
        }

        $user = Auth::user();
        $current = $prefs->forUser($user)[$category];

        if ($channel === 'sms' && ! $current['sms_supported']) {
            return;
        }

        $prefs->set(
            $user,
            $category,
            $channel === 'in_app' ? ! $current['in_app'] : $current['in_app'],
            $channel === 'sms' ? ! $current['sms'] : $current['sms'],
        );
    }

    public function render()
    {
        $user = Auth::user();

        $query = $this->onlyUnread ? $user->unreadNotifications() : $user->notifications();

        $items = $query->latest()
            ->limit((int) config('notifications.center_limit', 60))
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'message' => (string) ($n->data['message'] ?? ''),
                'read' => $n->read_at !== null,
                'url' => NotificationLink::for((array) $n->data),
                'at' => $n->created_at,
            ]);

        return view('livewire.notifications.notification-center', [
            'items' => $items,
            'unread' => $user->unreadNotifications()->count(),
            'prefs' => app(NotificationPreferenceService::class)->forUser($user),
        ])->title(__('notification.title'));
    }
}
