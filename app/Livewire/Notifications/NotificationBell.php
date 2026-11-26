<?php

declare(strict_types=1);

namespace App\Livewire\Notifications;

use App\Support\NotificationLink;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Step 13 — زنگوله‌ی اعلان در سربرگ: شمارنده‌ی خوانده‌نشده + چند اعلان آخر.
 * فقط اعلان‌های خود کاربر (رابطه‌ی notifications) قابل دسترسی‌اند.
 */
class NotificationBell extends Component
{
    public function markRead(string $id): void
    {
        Auth::user()->notifications()->whereKey($id)->first()?->markAsRead();
    }

    public function markAllRead(): void
    {
        Auth::user()->unreadNotifications()->update(['read_at' => now()]);
    }

    public function render()
    {
        $user = Auth::user();

        $items = $user->notifications()
            ->latest()
            ->limit((int) config('notifications.bell_limit', 6))
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'message' => (string) ($n->data['message'] ?? ''),
                'read' => $n->read_at !== null,
                'url' => NotificationLink::for((array) $n->data),
                'at' => $n->created_at,
            ]);

        return view('livewire.notifications.notification-bell', [
            'unread' => $user->unreadNotifications()->count(),
            'items' => $items,
        ]);
    }
}
