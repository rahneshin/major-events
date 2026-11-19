<?php

use App\Livewire\Auth\CompleteProfile;
use App\Livewire\Auth\PhoneLogin;
use App\Livewire\Auth\VerifyOtp;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

if (app()->environment('local')) {
    Route::get('/styleguide', function () {
        return view('styleguide');
    })->name('styleguide');
}

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', PhoneLogin::class)->name('auth.login');
    Route::get('/verify', VerifyOtp::class)->name('auth.verify');
});

Route::middleware('auth')->group(function () {
    Route::get('/complete-profile', CompleteProfile::class)->name('auth.complete-profile');

    Route::post('/logout', function () {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('auth.login');
    })->name('auth.logout');
});
