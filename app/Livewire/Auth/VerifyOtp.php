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

    public function mount(): void
    {
        $this->phone = (string) session('otp_phone', '');

        if ($this->phone === '') {
            $this->redirect(route('auth.login'), navigate: true);
        }
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

        session()->forget('otp_phone');

        if (! $user->isProfileComplete()) {
            $this->redirect(route('auth.complete-profile'), navigate: true);

            return;
        }

        $this->redirect(route('home'), navigate: true);
    }

    public function resend(OtpService $otp): void
    {
        try {
            $otp->send(
                $this->phone,
                request()->ip(),
                request()->userAgent()
            );
        } catch (ValidationException $e) {
            throw $e;
        }

        $this->resendAvailableIn = 60;
        $this->dispatch('otp-resent');
    }

    public function render()
    {
        return view('livewire.auth.verify-otp');
    }
}
