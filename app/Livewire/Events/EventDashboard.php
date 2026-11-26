<?php

declare(strict_types=1);

namespace App\Livewire\Events;

use App\Models\Event;
use App\Services\DashboardService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Step 13 — داشبورد رویداد (فقط‌خواندنی). هر بخش همان قاعده‌ی دسترسی تب خودش را دارد
 * (مجموع بودجه ← BudgetLinePolicy@viewSummary، ریسک ← RiskPolicy@viewRegister).
 */
class EventDashboard extends Component
{
    public Event $event;

    public function mount(Event $event): void
    {
        $this->authorize('view', $event);
        $this->event = $event;
    }

    public function render()
    {
        $this->authorize('view', $this->event);

        return view('livewire.events.event-dashboard', [
            'd' => app(DashboardService::class)->forEvent($this->event, Auth::user()),
        ]);
    }
}
