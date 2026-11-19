<?php

namespace App\Livewire\Account;

use App\Enums\AccountType;
use App\Models\AccountUpgrade;
use App\Services\EntitlementService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('حساب من')]
class AccountPage extends Component
{
    public function upgrade(EntitlementService $entitlement): void
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user->isOrganizational()) {
            session()->flash('info', __('account.already_organizational'));

            return;
        }

        $from = $user->account_type;

        $user->forceFill(['account_type' => AccountType::Organizational])->save();

        AccountUpgrade::query()->create([
            'user_id' => $user->id,
            'from_type' => $from,
            'to_type' => AccountType::Organizational,
            'reason' => 'self_upgrade',
            'performed_by' => $user->id,
        ]);

        session()->flash('success', __('account.upgraded'));
    }

    public function render(EntitlementService $entitlement)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        return view('livewire.account.account-page', [
            'user' => $user,
            'plan' => $entitlement->planFor($user),
            'canUpgrade' => $user->isIndividual(),
        ]);
    }
}
