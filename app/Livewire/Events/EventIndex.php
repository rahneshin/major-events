<?php

declare(strict_types=1);

namespace App\Livewire\Events;

use App\Models\Event;
use App\Services\EntitlementService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class EventIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $filter = 'active';

    public function mount(): void
    {
        $this->authorize('viewAny', Event::class);
    }

    public function updatingFilter(): void
    {
        $this->resetPage();
    }

    public function render(EntitlementService $entitlement)
    {
        $user = Auth::user();

        $query = Event::query()
            ->visibleTo($user)
            ->with(['organization', 'creator'])
            ->latest();

        if ($this->filter === 'active') {
            $query->active();
        } elseif ($this->filter === 'archived') {
            $query->whereIn('status', ['archived', 'completed', 'cancelled']);
        }

        $events = $query->paginate(10);

        $limit = $entitlement->limit($user, 'active_events');
        $used = Event::query()->where('created_by', $user->id)->active()->count();

        return view('livewire.events.event-index', [
            'events' => $events,
            'limit' => $limit,
            'used' => $used,
            'canCreate' => $limit === null || $used < $limit,
        ])->title(__('event.index_title'));
    }
}
