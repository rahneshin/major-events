<?php

declare(strict_types=1);

namespace App\Livewire\Events;

use App\Models\Competition;
use App\Models\Event;
use App\Services\MedalTableService;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Component;

/**
 * Step 12E — جدول مدال رویداد (زیر فهرست ورزش‌ها). دیدن = هر کسی که رویداد را می‌بیند؛
 * تنظیم وزن/ترتیب = سطح مدیریت رویداد (`EventPolicy@update`).
 */
class EventMedals extends Component
{
    public Event $event;

    public bool $wEnabled = false;

    public string $wGold = '3';

    public string $wSilver = '2';

    public string $wBronze = '1';

    public string $wSort = 'medals';

    public ?string $error = null;

    public ?string $notice = null;

    public function mount(Event $event, MedalTableService $medals): void
    {
        $this->authorize('viewAny', [Competition::class, $event]);

        $this->event = $event;

        $weights = $medals->weights($event);
        if ($weights !== null) {
            $this->wEnabled = true;
            $this->wGold = (string) $weights['gold'];
            $this->wSilver = (string) $weights['silver'];
            $this->wBronze = (string) $weights['bronze'];
            $this->wSort = $medals->sortMode($event);
        }
    }

    public function saveSettings(MedalTableService $medals): void
    {
        $this->authorize('update', $this->event);
        $this->error = null;
        $this->notice = null;

        try {
            $this->event = $medals->configure(
                $this->event,
                Auth::user(),
                $this->wEnabled ? ['gold' => $this->wGold, 'silver' => $this->wSilver, 'bronze' => $this->wBronze] : null,
                $this->wEnabled ? $this->wSort : 'medals',
            );
            $this->notice = __('medals.saved');
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render(MedalTableService $medals)
    {
        return view('livewire.events.event-medals', [
            'table' => $medals->forEvent($this->event->fresh() ?? $this->event),
            'canConfigure' => Auth::user()->can('update', $this->event),
        ]);
    }
}
