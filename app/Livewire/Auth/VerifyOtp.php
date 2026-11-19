<?php

namespace App\Livewire\Auth;

use App\Services\OtpService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.guest')]
#[Title('تأیید کد')]
class VerifyOtp extends Component
{
    public string $phone = '';

    public string $code = '';

    public int $resendAvailableIn = 0;

    public ?string $debugCode = null;

    public function mount(): void
    {
        $this->phone = (string) session('otp_phone', '');

        if ($this->phone === '') {
            $this->redirect(route('auth.login'), navigate: true);

            return;
        }

        $this->debugCode = session('otp_debug_code');
    }

    public function verify(OtpService $otp): void
    {
        $this->validate([
            'code' => ['required', 'string', 'size:6'],
        ], [], [
            'code' => __('auth.code'),
        ]);

        try {
            $user = $otp->verify(
                $this->phone,
                $this->code,
                request()->ip()
            );
        } catch (ValidationException $e) {
            throw $e;
        }

        session()->forget(['otp_phone', 'otp_debug_code']);

        if (! $user->isProfileComplete()) {
            $this->redirect(route('auth.complete-profile'), navigate: true);

            return;
        }

        if (! $user->hasSelectedAccountType()) {
            $this->redirect(route('auth.select-account-type'), navigate: true);

            return;
        }

        $this->redirect(route('home'), navigate: true);
    }

    public function resend(OtpService $otp): void
    {
        try {
            $code = $otp->send(
                $this->phone,
                request()->ip(),
                request()->userAgent()
            );
        } catch (ValidationException $e) {
            throw $e;
        }

        if ($code !== null) {
            session()->put('otp_debug_code', $code);
            $this->debugCode = $code;
        }

        $this->resendAvailableIn = 60;
        $this->dispatch('otp-resent');
    }

    public function useDebugCode(): void
    {
        if ($this->debugCode) {
            $this->code = $this->debugCode;
        }
    }

    public function render()
    {
        return view('livewire.auth.verify-otp');
    }
}
