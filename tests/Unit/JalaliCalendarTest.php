<?php

use App\Support\JalaliCalendar;

it('knows the length of Jalali months', function () {
    foreach (range(1, 6) as $m) {
        expect(JalaliCalendar::daysInMonth(1405, $m))->toBe(31);
    }
    foreach (range(7, 11) as $m) {
        expect(JalaliCalendar::daysInMonth(1405, $m))->toBe(30);
    }

    // Esfand: 30 days only in a leap year (1403 is leap, 1404 is not)
    expect(JalaliCalendar::daysInMonth(1403, 12))->toBe(30)
        ->and(JalaliCalendar::daysInMonth(1404, 12))->toBe(29);
});

it('starts the week on Saturday (Farvardin 1405 begins on a Saturday)', function () {
    $month = JalaliCalendar::month(1405, 1);

    expect($month['offset'])->toBe(0)
        ->and($month['name'])->toBe('فروردین')
        ->and($month['days'])->toBe(31);
});

it('keeps the grid offset within a week for every month', function () {
    foreach (range(1, 12) as $m) {
        $month = JalaliCalendar::month(1405, $m);

        expect($month['offset'])->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(6)
            ->and($month['name'])->not->toBeEmpty();
    }
});

it('wraps prev/next across year boundaries', function () {
    expect(JalaliCalendar::month(1405, 1)['prev'])->toBe([1404, 12])
        ->and(JalaliCalendar::month(1405, 12)['next'])->toBe([1406, 1])
        ->and(JalaliCalendar::month(1405, 6)['prev'])->toBe([1405, 5])
        ->and(JalaliCalendar::month(1405, 6)['next'])->toBe([1405, 7]);
});

it('normalizes out-of-range months', function () {
    expect(JalaliCalendar::month(1405, 13)['year'])->toBe(1406)
        ->and(JalaliCalendar::month(1405, 13)['month'])->toBe(1)
        ->and(JalaliCalendar::month(1405, 0)['year'])->toBe(1404)
        ->and(JalaliCalendar::month(1405, 0)['month'])->toBe(12);
});

it('builds zero-padded Latin keys and a valid today', function () {
    expect(JalaliCalendar::key(1405, 9, 1))->toBe('1405/09/01');

    [$y, $m, $d] = JalaliCalendar::today();

    expect($y)->toBeGreaterThan(1400)
        ->and($m)->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(12)
        ->and($d)->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(31);
});
