<?php

declare(strict_types=1);

namespace App\Livewire\System;

use App\Enums\AccountType;
use App\Models\Organization;
use App\Models\User;
use App\Services\EntitlementService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class SystemDashboard extends Component
{
    public int $totalUsers = 0;

    public int $totalOrganizations = 0;

    public int $organizationalUsers = 0;

    public int $individualUsers = 0;

    public ?string $accountType = null;

    public array $limits = [];

    public array $progressSteps = [];

    public function mount(EntitlementService $entitlement): void
    {
        $this->authorize('access-admin');

        $this->totalUsers = User::query()->count();
        $this->totalOrganizations = Organization::query()->count();
        $this->organizationalUsers = User::query()
            ->where('account_type', AccountType::Organizational->value)
            ->count();
        $this->individualUsers = User::query()
            ->where('account_type', AccountType::Individual->value)
            ->count();

        $user = Auth::user();

        if ($user && $user->account_type) {
            $this->accountType = $user->account_type->value;
            $this->limits = [
                'active_events' => $entitlement->limit($user, 'active_events'),
                'organizations' => $entitlement->limit($user, 'organizations'),
                'committees_per_event' => $entitlement->limit($user, 'committees_per_event'),
                'members_per_event' => $entitlement->limit($user, 'members_per_event'),
                'tasks_per_event' => $entitlement->limit($user, 'tasks_per_event'),
            ];
        }

        $this->progressSteps = [
            ['num' => '00', 'title' => 'نصب و ابزارها', 'done' => true],
            ['num' => '01', 'title' => 'UI / RTL / جلالی', 'done' => true],
            ['num' => '02', 'title' => 'ورود OTP', 'done' => true],
            ['num' => '03', 'title' => 'نوع حساب و Entitlement', 'done' => true],
            ['num' => '04', 'title' => 'سازمان مجازی', 'done' => true],
            ['num' => '05', 'title' => 'رویداد (هسته)', 'done' => true],
            ['num' => '06', 'title' => 'اعضای رویداد', 'done' => true],
            ['num' => '07', 'title' => 'کمیته‌ها', 'done' => true],
            ['num' => '08', 'title' => 'وظایف و زمان‌بندی', 'done' => false],
        ];
    }

    public function render()
    {
        return view('livewire.system.system-dashboard')
            ->title(__('ui.system_dashboard'));
    }
}
