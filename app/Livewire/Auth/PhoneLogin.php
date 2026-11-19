<?php

namespace App\Livewire\Auth;

use App\Services\OtpService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.guest')]
#[Title('ورود')]
class PhoneLogin extends Component
{
    public string $phone = '';

    public function sendCode(OtpService $otp): void
    {
        $this->validate([
            'phone' => ['required', 'string', 'max:20'],
        ], [], [
            'phone' => __('ui.phone'),
        ]);

        try {
            $otp->send(
                $this->phone,
                request()->ip(),
                request()->userAgent()
            );
        } catch (ValidationException $e) {
            throw $e;
        }

        session()->put('otp_phone', $this->phone);

        $this->redirect(route('auth.verify'), navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.phone-login');
    }
}
