<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.guest')]
#[Title('تکمیل پروفایل')]
class CompleteProfile extends Component
{
    public string $name = '';

    public function mount(): void
    {
        if (! Auth::check()) {
            $this->redirect(route('auth.login'), navigate: true);

            return;
        }

        if (Auth::user()->isProfileComplete()) {
            if (! Auth::user()->hasSelectedAccountType()) {
                $this->redirect(route('auth.select-account-type'), navigate: true);

                return;
            }

            $this->redirect(route('home'), navigate: true);
        }
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
        ], [], [
            'name' => __('ui.name'),
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $user->forceFill(['name' => trim($this->name)])->save();

        $this->redirect(route('auth.select-account-type'), navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.complete-profile');
    }
}
