<?php

namespace App\Livewire\Orgs;

use App\Enums\OrgRole;
use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('جزئیات سازمان')]
class OrganizationShow extends Component
{
    use AuthorizesRequests;

    public Organization $organization;

    public string $invitePhone = '';

    public string $inviteRole = 'member';

    public function mount(Organization $organization): void
    {
        $this->authorize('view', $organization);
        $this->organization = $organization->load(['owner', 'members']);
    }

    public function invite(OrganizationService $service): void
    {
        $this->authorize('invite', $this->organization);

        $this->validate([
            'invitePhone' => ['required', 'string', 'max:20'],
            'inviteRole' => ['required', 'in:admin,manager,member,viewer'],
        ], [], [
            'invitePhone' => __('ui.phone'),
            'inviteRole' => __('org.role'),
        ]);

        $service->invite(
            $this->organization,
            Auth::user(),
            $this->invitePhone,
            OrgRole::from($this->inviteRole),
        );

        $this->reset('invitePhone');
        $this->inviteRole = 'member';
        session()->flash('success', __('org.invite_sent'));
        $this->organization->refresh()->load(['owner', 'members']);
    }

    public function changeRole(int $userId, string $role, OrganizationService $service): void
    {
        $this->authorize('manageMembers', $this->organization);

        $target = User::query()->findOrFail($userId);
        $service->changeMemberRole($this->organization, $target, OrgRole::from($role), Auth::user());

        session()->flash('success', __('org.role_updated'));
        $this->organization->refresh()->load(['owner', 'members']);
    }

    public function removeMember(int $userId, OrganizationService $service): void
    {
        $this->authorize('manageMembers', $this->organization);

        $target = User::query()->findOrFail($userId);
        $service->removeMember($this->organization, $target, Auth::user());

        session()->flash('success', __('org.member_removed'));
        $this->organization->refresh()->load(['owner', 'members']);
    }

    public function leave(OrganizationService $service): void
    {
        $service->leave($this->organization, Auth::user());
        session()->flash('success', __('org.left'));
        $this->redirect(route('orgs.index'), navigate: false);
    }

    public function transferTo(int $userId, OrganizationService $service): void
    {
        $this->authorize('transferOwnership', $this->organization);

        $target = User::query()->findOrFail($userId);
        $service->transferOwnership($this->organization, $target, Auth::user());

        session()->flash('success', __('org.ownership_transferred'));
        $this->organization->refresh()->load(['owner', 'members']);
    }

    public function render()
    {
        $user = Auth::user();

        return view('livewire.orgs.organization-show', [
            'canManage' => $user->can('manageMembers', $this->organization),
            'canUpdate' => $user->can('update', $this->organization),
            'canTransfer' => $user->can('transferOwnership', $this->organization),
            'isOwner' => $user->id === $this->organization->owner_id,
            'roles' => [
                OrgRole::Admin,
                OrgRole::Manager,
                OrgRole::Member,
                OrgRole::Viewer,
            ],
            'pendingInvites' => $this->organization->invitations()
                ->where('status', 'pending')
                ->where('expires_at', '>', now())
                ->latest()
                ->get(),
        ]);
    }
}
