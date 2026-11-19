<?php

namespace App\Livewire\Auth;

use App\Enums\AccountType;
use App\Models\AccountUpgrade;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.guest')]
#[Title('انتخاب نوع حساب')]
class SelectAccountType extends Component
{
    public ?string $selected = null;

    public function mount(): void
    {
        if (! Auth::check()) {
            $this->redirect(route('auth.login'), navigate: true);

            return;
        }

        $user = Auth::user();

        if (! $user->isProfileComplete()) {
            $this->redirect(route('auth.complete-profile'), navigate: true);

            return;
        }

        if ($user->hasSelectedAccountType()) {
            $this->redirect(route('home'), navigate: true);
        }
    }

    public function select(string $type): void
    {
        $this->selected = $type;
    }

    public function save(): void
    {
        $this->validate([
            'selected' => ['required', 'in:individual,organizational'],
        ], [], [
            'selected' => __('account.account_type'),
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $to = AccountType::from($this->selected);

        $user->forceFill(['account_type' => $to])->save();

        AccountUpgrade::query()->create([
            'user_id' => $user->id,
            'from_type' => null,
            'to_type' => $to,
            'reason' => 'onboarding',
            'performed_by' => $user->id,
        ]);

        session()->flash('success', __('account.type_selected'));

        $this->redirect(route('home'), navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.select-account-type', [
            'types' => AccountType::cases(),
        ]);
    }
}
