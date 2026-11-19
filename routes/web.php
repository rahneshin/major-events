<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

if (app()->environment('local')) {
    Route::get('/styleguide', function () {
        return view('styleguide');
    })->name('styleguide');
}
