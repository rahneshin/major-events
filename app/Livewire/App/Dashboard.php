<?php

declare(strict_types=1);

namespace App\Livewire\App;

use App\Models\Organization;
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
        return view('livewire.app.dashboard')
            ->title(__('ui.dashboard'));
    }
}
