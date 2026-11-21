<?php

declare(strict_types=1);

namespace App\Livewire\Events;

use App\Enums\CommitteeRole;
use App\Enums\CommitteeType;
use App\Models\Committee;
use App\Models\Event;
use App\Services\CommitteeService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class EventCommittees extends Component
{
    public Event $event;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $type = 'custom';

    public string $description = '';

    public ?int $head_user_id = null;

    /** @var array<int, int|null> committee_id => selected member id */
    public array $memberPicks = [];

    public ?string $error = null;

    public function mount(Event $event): void
    {
        $this->authorize('view', $event);
        $this->event = $event;
    }

    public function create(): void
    {
        $this->authorize('manageMembers', $this->event);
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $committeeId): void
    {
        $committee = $this->findCommittee($committeeId);
        $this->authorize('update', $committee);

        $this->editingId = $committee->id;
        $this->name = $committee->name;
        $this->type = $committee->type->value;
        $this->description = $committee->description ?? '';
        $this->head_user_id = $committee->head_user_id;
        $this->showForm = true;
        $this->error = null;
    }

    public function save(CommitteeService $service): void
    {
        $this->authorize('manageMembers', $this->event);
        $this->error = null;

        $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', 'in:'.implode(',', array_column(CommitteeType::cases(), 'value'))],
            'description' => ['nullable', 'string', 'max:1000'],
            'head_user_id' => ['nullable', 'integer'],
        ]);

        try {
            $data = [
                'name' => $this->name,
                'type' => CommitteeType::from($this->type),
                'description' => $this->description ?: null,
                'head_user_id' => $this->head_user_id,
            ];

            if ($this->editingId) {
                $committee = $this->findCommittee($this->editingId);
                $service->update($this->event, Auth::user(), $committee, $data);
                session()->flash('success', __('committee.updated'));
            } else {
                $service->create($this->event, Auth::user(), $data);
                session()->flash('success', __('committee.created'));
            }

            $this->resetForm();
            $this->showForm = false;
            $this->event->load('committees.members');
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $committeeId, CommitteeService $service): void
    {
        $committee = $this->findCommittee($committeeId);
        $this->authorize('delete', $committee);

        try {
            $service->delete($this->event, Auth::user(), $committee);
            $this->event->load('committees.members');
            session()->flash('success', __('committee.deleted'));
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function addMember(int $committeeId, CommitteeService $service): void
    {
        $committee = $this->findCommittee($committeeId);
        $this->authorize('manageMembers', $committee);
        $this->error = null;

        $memberId = $this->memberPicks[$committeeId] ?? null;
        if (! $memberId) {
            $this->error = __('committee.select_member');

            return;
        }

        try {
            $user = $this->event->members()->whereKey($memberId)->firstOrFail();
            $service->addMember($this->event, Auth::user(), $committee, $user);
            unset($this->memberPicks[$committeeId]);
            $this->event->load('committees.members');
            session()->flash('success', __('committee.member_added'));
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function removeMember(int $committeeId, int $userId, CommitteeService $service): void
    {
        $committee = $this->findCommittee($committeeId);
        $this->authorize('manageMembers', $committee);
        $this->error = null;

        try {
            $user = $this->event->members()->whereKey($userId)->firstOrFail();
            $service->removeMember($this->event, Auth::user(), $committee, $user);
            $this->event->load('committees.members');
            session()->flash('success', __('committee.member_removed'));
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function applyTemplate(CommitteeService $service): void
    {
        $this->authorize('manageMembers', $this->event);
        $this->error = null;

        $created = 0;

        foreach (CommitteeType::templateFor($this->event->event_type) as $type) {
            if ($this->event->committees()->where('type', $type->value)->exists()) {
                continue;
            }

            try {
                $service->create($this->event, Auth::user(), [
                    'name' => $type->label(),
                    'type' => $type,
                ]);
                $created++;
            } catch (\Throwable $e) {
                $this->error = $e->getMessage();

                break;
            }
        }

        $this->event->load('committees.members');
        session()->flash('success', __('committee.template_applied', ['count' => $created]));
    }

    private function findCommittee(int $id): Committee
    {
        return $this->event->committees()->with(['members', 'head'])->findOrFail($id);
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->type = 'custom';
        $this->description = '';
        $this->head_user_id = null;
        $this->error = null;
        $this->resetValidation();
    }

    public function render()
    {
        $this->event->loadMissing(['committees.members', 'members']);

        return view('livewire.events.event-committees', [
            'committees' => $this->event->committees,
            'eventMembers' => $this->event->members,
            'types' => CommitteeType::cases(),
            'canManage' => Auth::user()->can('manageMembers', $this->event),
        ])->title($this->event->title);
    }
}
