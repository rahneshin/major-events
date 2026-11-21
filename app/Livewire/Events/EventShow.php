<?php

declare(strict_types=1);

namespace App\Livewire\Events;

use App\Enums\EventRole;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use App\Services\EventMemberService;
use App\Services\EventService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class EventShow extends Component
{
    public Event $event;

    public string $tab = 'overview';

    public string $invitePhone = '';

    public string $inviteRole = 'member';

    public ?string $memberError = null;

    public function mount(Event $event): void
    {
        $this->authorize('view', $event);
        $this->event = $event->load(['goals', 'organization', 'creator', 'members']);

        $requested = request()->query('tab');
        if (is_string($requested) && in_array($requested, ['overview', 'members', 'committees', 'tasks', 'budget', 'risk'], true)) {
            $this->tab = $requested;
        }
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
        $this->memberError = null;
    }

    public function transitionTo(string $status, EventService $service): void
    {
        $this->authorize('transition', $this->event);

        $target = EventStatus::from($status);
        $service->transition($this->event, $target);
        $this->event->refresh();

        session()->flash('success', __('event.status_changed', ['status' => $target->label()]));
    }

    public function invite(EventMemberService $members): void
    {
        $this->authorize('manageMembers', $this->event);
        $this->memberError = null;

        $this->validate([
            'invitePhone' => ['required', 'string', 'min:10', 'max:15'],
            'inviteRole' => ['required', 'in:event_manager,committee_head,member,viewer'],
        ]);

        try {
            $members->inviteByPhone(
                $this->event,
                Auth::user(),
                $this->invitePhone,
                EventRole::from($this->inviteRole),
            );
            $this->invitePhone = '';
            $this->event->load('members');
            session()->flash('success', __('event.invite_sent'));
        } catch (\Throwable $e) {
            $this->memberError = $e->getMessage();
        }
    }

    public function changeRole(int $userId, string $role, EventMemberService $members): void
    {
        $this->authorize('manageMembers', $this->event);
        $this->memberError = null;

        try {
            $user = User::query()->findOrFail($userId);
            $members->changeRole($this->event, Auth::user(), $user, EventRole::from($role));
            $this->event->load('members');
            session()->flash('success', __('event.role_updated'));
        } catch (\Throwable $e) {
            $this->memberError = $e->getMessage();
        }
    }

    public function removeMember(int $userId, EventMemberService $members): void
    {
        $this->authorize('manageMembers', $this->event);
        $this->memberError = null;

        try {
            $user = User::query()->findOrFail($userId);
            $members->removeMember($this->event, Auth::user(), $user);
            $this->event->load('members');
            session()->flash('success', __('event.member_removed'));
        } catch (\Throwable $e) {
            $this->memberError = $e->getMessage();
        }
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
            'canManageMembers' => Auth::user()->can('manageMembers', $this->event),
            'roles' => EventRole::cases(),
        ])->title($this->event->title);
    }
}
