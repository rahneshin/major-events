<?php

namespace App\Livewire\Orgs;

use App\Enums\OrganizationType;
use App\Models\Organization;
use App\Services\OrganizationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('سازمان')]
class OrganizationForm extends Component
{
    use AuthorizesRequests;

    public ?int $organizationId = null;

    public string $name = '';

    public string $type = 'other';

    public string $city = '';

    public string $description = '';

    public function mount(?Organization $organization = null): void
    {
        if ($organization?->exists) {
            $this->authorize('update', $organization);
            $this->organizationId = $organization->id;
            $this->name = $organization->name;
            $this->type = $organization->type->value;
            $this->city = (string) $organization->city;
            $this->description = (string) $organization->description;
        } else {
            $this->authorize('create', Organization::class);
        }
    }

    public function save(OrganizationService $service): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'type' => ['required', 'in:'.implode(',', array_column(OrganizationType::cases(), 'value'))],
            'city' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'name' => __('org.name'),
            'type' => __('org.type'),
            'city' => __('org.city'),
            'description' => __('org.description'),
        ]);

        if ($this->organizationId) {
            $org = Organization::query()->findOrFail($this->organizationId);
            $this->authorize('update', $org);
            $service->update($org, $data);
            session()->flash('success', __('org.updated'));
            $this->redirect(route('orgs.show', $org), navigate: false);

            return;
        }

        $org = $service->create(Auth::user(), $data);
        session()->flash('success', __('org.created'));
        $this->redirect(route('orgs.show', $org), navigate: false);
    }

    public function render()
    {
        return view('livewire.orgs.organization-form', [
            'types' => OrganizationType::cases(),
            'isEdit' => $this->organizationId !== null,
        ]);
    }
}
