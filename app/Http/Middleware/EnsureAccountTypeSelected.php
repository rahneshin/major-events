<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountTypeSelected
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        // Profile name first
        if (! $user->isProfileComplete()
            && ! $request->routeIs('auth.complete-profile', 'auth.logout')
        ) {
            return redirect()->route('auth.complete-profile');
        }

        // Then account type onboarding
        if ($user->isProfileComplete()
            && $user->account_type === null
            && ! $request->routeIs('auth.select-account-type', 'auth.logout', 'auth.complete-profile')
        ) {
            return redirect()->route('auth.select-account-type');
        }

        return $next($request);
    }
}
