<?php

declare(strict_types=1);

namespace App\Livewire\Events;

use App\Enums\CommitteeRole;
use App\Enums\CommitteeType;
use App\Exceptions\LimitExceededException;
use App\Models\Committee;
use App\Models\Event;
use App\Models\User;
use App\Services\CommitteeService;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Component;

class EventCommittees extends Component
{
    public Event $event;

    public ?int $selectedId = null;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $type = 'custom';

    public string $description = '';

    public string $color = '#6366f1';

    public string $headUserId = '';

    public string $memberUserId = '';

    public string $memberPhone = '';

    public string $descriptionDraft = '';

    public ?string $error = null;

    public ?string $notice = null;

    public function mount(Event $event): void
    {
        $this->authorize('view', $event);
        $this->event = $event;
    }

    public function updatedType(string $value): void
    {
        $type = CommitteeType::tryFrom($value);

        if ($type && ! $this->editingId) {
            $this->color = $type->color();
            if ($this->name === '' && $type !== CommitteeType::Custom) {
                $this->name = $type->label();
            }
        }
    }

    public function openCreate(): void
    {
        $this->authorize('create', [Committee::class, $this->event]);
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $committee = $this->findCommittee($id);
        $this->authorize('update', $committee);

        $this->editingId = $committee->id;
        $this->name = $committee->name;
        $this->type = $committee->type->value;
        $this->description = (string) $committee->description;
        $this->color = $committee->color ?? $committee->type->color();
        $this->headUserId = (string) ($committee->head_user_id ?? '');
        $this->showForm = true;
        $this->error = null;
        $this->notice = null;
    }

    public function cancelForm(): void
    {
        $this->resetForm();
    }

    public function save(CommitteeService $service): void
    {
        $this->error = null;
        $this->notice = null;

        $this->validate([
            'name' => ['required', 'string', 'max:80'],
            'type' => ['required', 'in:'.implode(',', array_column(CommitteeType::cases(), 'value'))],
            'description' => ['nullable', 'string', 'max:1000'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'headUserId' => ['nullable', 'integer'],
        ]);

        $data = [
            'name' => $this->name,
            'type' => $this->type,
            'description' => $this->description !== '' ? $this->description : null,
            'color' => $this->color,
            'head_user_id' => $this->headUserId !== '' ? (int) $this->headUserId : null,
        ];

        try {
            if ($this->editingId) {
                $committee = $this->findCommittee($this->editingId);
                $this->authorize('update', $committee);
                $service->update($committee, $data, Auth::user());
                $this->notice = __('committee.updated');
            } else {
                $this->authorize('create', [Committee::class, $this->event]);
                $service->create($this->event, Auth::user(), $data);
                $this->notice = __('committee.created');
            }
            $this->resetForm();
        } catch (LimitExceededException|InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function applyTemplate(CommitteeService $service): void
    {
        $this->authorize('create', [Committee::class, $this->event]);
        $this->error = null;
        $this->notice = null;

        try {
            $created = $service->applyTemplate($this->event, Auth::user());
            $this->notice = __('committee.template_applied', ['count' => $created->count()]);
        } catch (LimitExceededException|InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function move(int $id, string $direction, CommitteeService $service): void
    {
        $committee = $this->findCommittee($id);
        $this->authorize('reorder', [Committee::class, $this->event]);

        $service->move($committee, $direction === 'up' ? 'up' : 'down');
    }

    public function delete(int $id, CommitteeService $service): void
    {
        $committee = $this->findCommittee($id);
        $this->authorize('delete', $committee);
        $this->error = null;
        $this->notice = null;

        try {
            $service->delete($committee);
            if ($this->selectedId === $id) {
                $this->selectedId = null;
            }
            $this->notice = __('committee.deleted');
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function select(?int $id): void
    {
        if ($id !== null) {
            $committee = $this->findCommittee($id);
            $this->authorize('view', $committee);
            $this->descriptionDraft = (string) $committee->description;
        }

        $this->selectedId = $id;
        $this->error = null;
        $this->notice = null;
        $this->memberUserId = '';
        $this->memberPhone = '';
    }

    public function saveDescription(CommitteeService $service): void
    {
        $committee = $this->selectedCommittee();
        $this->authorize('updateDescription', $committee);

        $this->validate(['descriptionDraft' => ['nullable', 'string', 'max:1000']]);

        $service->updateDescription($committee, $this->descriptionDraft !== '' ? $this->descriptionDraft : null);
        $this->notice = __('committee.updated');
    }

    public function addMember(CommitteeService $service): void
    {
        $committee = $this->selectedCommittee();
        $this->authorize('manageMembers', $committee);
        $this->error = null;
        $this->notice = null;

        $this->validate(['memberUserId' => ['required', 'integer']]);

        try {
            // Only users who already belong to this event can be picked here.
            $user = $this->event->members()->where('users.id', (int) $this->memberUserId)->firstOrFail();
            $service->addMember($committee, Auth::user(), $user);
            $this->memberUserId = '';
            $this->notice = __('committee.member_added');
        } catch (LimitExceededException|InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            $this->error = __('committee.must_be_event_member');
        }
    }

    public function addByPhone(CommitteeService $service): void
    {
        $committee = $this->selectedCommittee();
        $this->authorize('manageMembers', $committee);
        $this->error = null;
        $this->notice = null;

        $this->validate(['memberPhone' => ['required', 'string', 'min:10', 'max:15']]);

        $normalized = PhoneNumber::normalize($this->memberPhone);
        $user = $normalized ? User::query()->where('phone', $normalized)->first() : null;

        if (! $user) {
            $this->error = __('committee.user_not_found');

            return;
        }

        // Adding a person who is not yet an event member needs event-level rights too.
        if (! $this->event->hasMember($user)) {
            $this->authorize('manageMembers', $this->event);
        }

        try {
            $service->addMember($committee, Auth::user(), $user, CommitteeRole::Member, addToEvent: true);
            $this->memberPhone = '';
            $this->notice = __('committee.member_added');
        } catch (LimitExceededException|InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function removeMember(int $userId, CommitteeService $service): void
    {
        $committee = $this->selectedCommittee();
        $this->authorize('manageMembers', $committee);
        $this->error = null;
        $this->notice = null;

        try {
            $service->removeMember($committee, User::query()->findOrFail($userId));
            $this->notice = __('committee.member_removed');
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function makeHead(int $userId, CommitteeService $service): void
    {
        $committee = $this->selectedCommittee();
        // Assigning the head is an event-level decision (not for the head itself).
        $this->authorize('update', $committee);
        $this->error = null;
        $this->notice = null;

        try {
            $service->setHead($committee, User::query()->findOrFail($userId));
            $this->notice = __('committee.head_set');
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render()
    {
        $user = Auth::user();

        $committees = $this->event->committees()
            ->with('head')
            ->withCount('members')
            ->get();

        $selected = $this->selectedId ? $committees->firstWhere('id', $this->selectedId) : null;
        $selectedMembers = collect();
        $candidates = collect();

        if ($selected) {
            $selectedMembers = $selected->members()->orderBy('name')->get();
            $candidates = $this->event->members()
                ->whereNotIn('users.id', $selectedMembers->pluck('id'))
                ->orderBy('name')
                ->get();
        }

        return view('livewire.events.event-committees', [
            'committees' => $committees,
            'selected' => $selected,
            'selectedMembers' => $selectedMembers,
            'candidates' => $candidates,
            'types' => CommitteeType::cases(),
            'eventMembers' => $this->event->members()->orderBy('name')->get(),
            'canCreate' => $user->can('create', [Committee::class, $this->event]),
            'canManageEvent' => $user->can('update', $this->event),
            'canManageSelected' => $selected ? $user->can('manageMembers', $selected) : false,
            'canEditDescription' => $selected ? $user->can('updateDescription', $selected) : false,
            'limit' => app(\App\Services\EntitlementService::class)->limit($user, 'committees_per_event'),
        ]);
    }

    private function findCommittee(int $id): Committee
    {
        return $this->event->committees()->whereKey($id)->firstOrFail();
    }

    private function selectedCommittee(): Committee
    {
        abort_if($this->selectedId === null, 404);

        return $this->findCommittee($this->selectedId);
    }

    private function resetForm(): void
    {
        $this->reset(['showForm', 'editingId', 'name', 'description', 'headUserId']);
        $this->type = 'custom';
        $this->color = '#6366f1';
        $this->error = null;
        $this->resetValidation();
    }
}
