<?php

use App\Support\Jalali;
use Illuminate\Support\Carbon;

it('formats carbon to jalali', function () {
    $date = Carbon::create(2026, 11, 19, 0, 0, 0, 'Asia/Tehran');
    $result = Jalali::format($date, 'Y/m/d');

    expect($result)->not->toBeNull();
    expect($result)->toMatch('/^\d{4}\/\d{2}\/\d{2}$/');
});

it('parses jalali string back to carbon', function () {
    $carbon = Jalali::parse('1405/08/28');

    expect($carbon)->toBeInstanceOf(Carbon::class);
});

it('returns null for invalid jalali string', function () {
    expect(Jalali::parse('invalid'))->toBeNull();
    expect(Jalali::parse(''))->toBeNull();
});
