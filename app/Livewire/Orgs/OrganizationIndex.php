<?php

namespace App\Livewire\Orgs;

use App\Models\Organization;
use App\Services\OrganizationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('سازمان‌ها')]
class OrganizationIndex extends Component
{
    use AuthorizesRequests;

    public function mount(): void
    {
        $this->authorize('viewAny', Organization::class);
    }

    public function switchTo(int $organizationId, OrganizationService $service): void
    {
        $org = Organization::query()->findOrFail($organizationId);
        $this->authorize('view', $org);

        $service->switchContext(Auth::user(), $org);

        session()->flash('success', __('org.switched', ['name' => $org->name]));
    }

    public function render()
    {
        $user = Auth::user();

        $organizations = $user->organizations()->with('owner')->orderBy('name')->get();

        return view('livewire.orgs.organization-index', [
            'organizations' => $organizations,
            'canCreate' => $user->can('create', Organization::class),
            'currentId' => $user->current_organization_id ?? session('current_organization_id'),
        ]);
    }
}
