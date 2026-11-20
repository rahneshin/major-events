<?php

use App\Livewire\Account\AccountPage;
use App\Livewire\Auth\CompleteProfile;
use App\Livewire\Auth\PhoneLogin;
use App\Livewire\Auth\SelectAccountType;
use App\Livewire\Auth\VerifyOtp;
use App\Livewire\Orgs\OrganizationForm;
use App\Livewire\Orgs\OrganizationIndex;
use App\Livewire\Orgs\OrganizationShow;
use App\Livewire\System\SystemDashboard;
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
    Route::get('/select-account-type', SelectAccountType::class)->name('auth.select-account-type');

    Route::post('/logout', function () {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('auth.login');
    })->name('auth.logout');

    Route::middleware('account.type')->group(function () {
        Route::get('/account', AccountPage::class)->name('account.index');

        Route::get('/system', SystemDashboard::class)->name('system.dashboard');

        Route::get('/organizations', OrganizationIndex::class)->name('orgs.index');
        Route::get('/organizations/create', OrganizationForm::class)->name('orgs.create');
        Route::get('/organizations/{organization}', OrganizationShow::class)->name('orgs.show');
        Route::get('/organizations/{organization}/edit', OrganizationForm::class)->name('orgs.edit');
    });
});
