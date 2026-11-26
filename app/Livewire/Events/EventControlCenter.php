<?php

declare(strict_types=1);

namespace App\Livewire\Events;

use App\Models\Event;
use App\Services\AnnouncementService;
use App\Services\DashboardService;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Component;

/**
 * Step 13 — «مرکز کنترل روز رویداد» (نسخه‌ی اولیه):
 * وضعیت وظایف فاز «روز رویداد»، حوادث باز و اطلاعیه‌ی سریع به اعضا.
 * دیدن = `view`؛ ارسال اطلاعیه = `update` (مدیر رویداد).
 */
class EventControlCenter extends Component
{
    public Event $event;

    public string $announcement = '';

    public bool $urgent = false;

    public ?string $error = null;

    public function mount(Event $event): void
    {
        $this->authorize('view', $event);
        $this->event = $event;
    }

    public function publish(AnnouncementService $service): void
    {
        $this->authorize('update', $this->event);
        $this->error = null;

        try {
            $service->publish($this->event, Auth::user(), $this->announcement, $this->urgent);
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->announcement = '';
        $this->urgent = false;
        session()->flash('success', __('notification.announcement_sent'));
    }

    public function render()
    {
        $this->authorize('view', $this->event);

        return view('livewire.events.event-control-center', [
            'c' => app(DashboardService::class)->controlCenter($this->event),
            'canAnnounce' => Auth::user()->can('update', $this->event) && $this->event->isActive(),
        ]);
    }
}
