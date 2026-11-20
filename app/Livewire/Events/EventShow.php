<?php

declare(strict_types=1);

namespace App\Livewire\Events;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Services\EventService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class EventShow extends Component
{
    public Event $event;

    public string $tab = 'overview';

    public function mount(Event $event): void
    {
        $this->authorize('view', $event);
        $this->event = $event->load(['goals', 'organization', 'creator']);
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
    }

    public function transitionTo(string $status, EventService $service): void
    {
        $this->authorize('transition', $this->event);

        $target = EventStatus::from($status);
        $service->transition($this->event, $target);
        $this->event->refresh();

        session()->flash('success', __('event.status_changed', ['status' => $target->label()]));
    }

    public function deleteEvent(): void
    {
        $this->authorize('delete', $this->event);
        $this->event->delete();
        session()->flash('success', __('event.deleted'));
        $this->redirect(route('events.index'), navigate: true);
    }

    public function render()
    {
        $transitions = $this->event->status->allowedTransitions();

        return view('livewire.events.event-show', [
            'transitions' => $transitions,
            'canUpdate' => Auth::user()->can('update', $this->event),
            'canDelete' => Auth::user()->can('delete', $this->event),
        ])->title($this->event->title);
    }
}
