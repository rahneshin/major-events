<?php

use App\Livewire\Account\AccountPage;
use App\Livewire\Admin\Dashboard as AdminDashboard;
use App\Livewire\App\Dashboard as AppDashboard;
use App\Livewire\Auth\CompleteProfile;
use App\Livewire\Auth\PhoneLogin;
use App\Livewire\Auth\SelectAccountType;
use App\Livewire\Auth\VerifyOtp;
use App\Livewire\Events\EventCommittees;
use App\Livewire\Events\EventForm;
use App\Livewire\Events\EventIndex;
use App\Livewire\Events\EventShow;
use App\Livewire\Orgs\OrganizationForm;
use App\Livewire\Orgs\OrganizationIndex;
use App\Livewire\Orgs\OrganizationShow;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public site
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return view('public.home');
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
        Route::get('/app', AppDashboard::class)->name('app.dashboard');
        Route::get('/account', AccountPage::class)->name('account.index');

        Route::get('/organizations', OrganizationIndex::class)->name('orgs.index');
        Route::get('/organizations/create', OrganizationForm::class)->name('orgs.create');
        Route::get('/organizations/{organization}', OrganizationShow::class)->name('orgs.show');
        Route::get('/organizations/{organization}/edit', OrganizationForm::class)->name('orgs.edit');

        Route::get('/events', EventIndex::class)->name('events.index');
        Route::get('/events/create', EventForm::class)->name('events.create');
        Route::get('/events/{event}', EventShow::class)->name('events.show');
        Route::get('/events/{event}/edit', EventForm::class)->name('events.edit');
        Route::get('/events/{event}/committees', EventCommittees::class)->name('events.committees');

        Route::get('/admin', AdminDashboard::class)->name('admin.dashboard');
    });
});
