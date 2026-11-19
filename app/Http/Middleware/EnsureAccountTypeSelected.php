<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountTypeSelected
{
    public function handle(Request $request, Closure $next): Response
    {
        // هر درخواست Livewire (update / upload / ...) نباید توسط onboarding ریدایرکت شود
        if ($this->isLivewireRequest($request)) {
            return $next($request);
        }

        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        // مسیرهای مجاز در حین onboarding
        $allowed = [
            'auth.complete-profile',
            'auth.select-account-type',
            'auth.logout',
            'auth.login',
            'auth.verify',
        ];

        if ($request->routeIs(...$allowed)) {
            return $next($request);
        }

        // اول نام
        if (! $user->isProfileComplete()) {
            return redirect()->route('auth.complete-profile');
        }

        // بعد نوع حساب
        if ($user->account_type === null) {
            return redirect()->route('auth.select-account-type');
        }

        return $next($request);
    }

    private function isLivewireRequest(Request $request): bool
    {
        if ($request->hasHeader('X-Livewire')) {
            return true;
        }

        if ($request->is('livewire/*')) {
            return true;
        }

        if ($request->routeIs('livewire.*')) {
            return true;
        }

        // Livewire v3/v4 payload
        if ($request->has('_token') && $request->has('components')) {
            return true;
        }

        return false;
    }
}
