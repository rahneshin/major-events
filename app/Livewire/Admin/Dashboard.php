<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\AccountType;
use App\Models\Organization;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin')]
class Dashboard extends Component
{
    public int $users = 0;

    public int $orgs = 0;

    public int $individual = 0;

    public int $organizational = 0;

    public function mount(): void
    {
        $this->authorize('access-admin');

        // Simple platform-wide stats for the system owner.
        // Full Filament admin comes in Step 20.
        $this->users = User::query()->count();
        $this->orgs = Organization::query()->count();
        $this->individual = User::query()->where('account_type', AccountType::Individual->value)->count();
        $this->organizational = User::query()->where('account_type', AccountType::Organizational->value)->count();
    }

    public function render()
    {
        return view('livewire.admin.dashboard')
            ->title('داشبورد ادمین');
    }
}
