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

it('writes a date in words without time', function () {
    $date = Jalali::parse('1405/04/09');

    expect(Jalali::long($date))->toBe('نهم تیر ۱۴۰۵')
        ->and(Jalali::long($date, false))->toBe('نهم تیر');
});

it('writes a date range and shows the year once when both ends share it', function () {
    $from = Jalali::parse('1405/04/09');
    $to = Jalali::parse('1405/08/07');
    $next = Jalali::parse('1406/01/21');

    expect(Jalali::range($from, $to))->toBe('نهم تیر تا هفتم آبان ۱۴۰۵')
        ->and(Jalali::range($from, $next))->toBe('نهم تیر ۱۴۰۵ تا بیست و یکم فروردین ۱۴۰۶');
});

it('accepts the ISO value produced by the date picker', function () {
    $iso = Jalali::parse('2026-11-23');

    expect($iso?->toDateString())->toBe('2026-11-23')
        ->and(Jalali::parse('2026-02-31'))->toBeNull();
});
