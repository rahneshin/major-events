<?php

declare(strict_types=1);

namespace App\Livewire\App;

use App\Models\Committee;
use App\Models\Event;
use App\Services\EntitlementService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Dashboard extends Component
{
    public string $greeting = '';

    public ?string $accountLabel = null;

    public int $orgCount = 0;

    /** @var array<string, int|null> */
    public array $limits = [];

    public function mount(EntitlementService $entitlement): void
    {
        $user = Auth::user();
        $hour = (int) now('Asia/Tehran')->format('H');

        $this->greeting = match (true) {
            $hour < 12 => 'صبح بخیر',
            $hour < 17 => 'ظهر بخیر',
            default => 'عصر بخیر',
        };

        if ($user?->account_type) {
            $this->accountLabel = $user->account_type->value === 'organizational' ? 'سازمانی' : 'عادی';
            $this->limits = [
                'active_events' => $entitlement->limit($user, 'active_events'),
                'organizations' => $entitlement->limit($user, 'organizations'),
                'committees_per_event' => $entitlement->limit($user, 'committees_per_event'),
            ];
            $this->orgCount = $user->organizations()->count();
        }
    }

    public function render()
    {
        $user = Auth::user();

        $visible = fn () => Event::query()->visibleTo($user);

        $activeCount = $visible()->active()->count();

        $events = $visible()
            ->active()
            ->with('organization')
            ->withCount(['members', 'committees'])
            ->orderByRaw('start_date IS NULL')
            ->orderBy('start_date')
            ->limit(5)
            ->get();

        $nextEvent = $visible()
            ->active()
            ->whereDate('start_date', '>=', now('Asia/Tehran')->toDateString())
            ->orderBy('start_date')
            ->first();

        $committeeCount = Committee::query()
            ->whereIn('event_id', $visible()->active()->select('events.id'))
            ->count();

        // سقف رویداد فعال: همان منطق EventIndex
        $eventLimit = $this->limits['active_events'] ?? null;
        $usedEvents = Event::query()->where('created_by', $user->id)->active()->count();
        $canCreate = $eventLimit === null || $usedEvents < $eventLimit;

        // چک‌لیست شروع (کاملاً از داده‌ی واقعی)
        $hasEvent = $visible()->exists();
        $hasInvitedMember = $visible()
            ->whereHas('members', fn ($q) => $q->where('users.id', '!=', $user->id))
            ->exists();
        $hasCommittee = $visible()->whereHas('committees')->exists();

        $checklist = [
            ['t' => 'تکمیل نام در حساب', 'done' => filled($user->name), 'href' => route('account.index'), 'cta' => 'تکمیل'],
            ['t' => 'ساخت اولین رویداد', 'done' => $hasEvent, 'href' => $canCreate ? route('events.create') : route('events.index'), 'cta' => 'ساخت'],
            ['t' => 'دعوت یک همکار به رویداد', 'done' => $hasInvitedMember, 'href' => route('events.index'), 'cta' => 'دعوت'],
            ['t' => 'ساخت اولین کمیته', 'done' => $hasCommittee, 'href' => route('events.index'), 'cta' => 'ساخت'],
        ];
        $doneCount = collect($checklist)->where('done', true)->count();

        return view('livewire.app.dashboard', [
            'events' => $events,
            'nextEvent' => $nextEvent,
            'activeCount' => $activeCount,
            'committeeCount' => $committeeCount,
            'eventLimit' => $eventLimit,
            'usedEvents' => $usedEvents,
            'canCreate' => $canCreate,
            'checklist' => $checklist,
            'doneCount' => $doneCount,
        ])->title(__('ui.dashboard'));
    }
}
