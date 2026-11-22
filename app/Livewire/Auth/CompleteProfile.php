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
            $this->redirect(route('auth.login'));

            return;
        }

        $user = Auth::user();

        if ($user->isProfileComplete()) {
            if (! $user->hasSelectedAccountType()) {
                $this->redirect(route('auth.select-account-type'));

                return;
            }

            $this->redirect(route('home'));
        }
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
        ], [
            'name.required' => 'وارد کردن نام الزامی است.',
            'name.min' => 'نام باید حداقل ۲ کاراکتر باشد.',
        ], [
            'name' => __('ui.name'),
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $user->forceFill([
            'name' => trim($validated['name']),
        ])->save();

        $this->redirect(route($user->hasSelectedAccountType() ? 'app.dashboard' : 'auth.select-account-type'));
    }

    public function render()
    {
        return view('livewire.auth.complete-profile');
    }
}
