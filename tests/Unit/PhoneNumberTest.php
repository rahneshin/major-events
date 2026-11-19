<?php

use App\Support\PhoneNumber;

it('normalizes standard iranian mobile numbers', function (string $input, string $expected) {
    expect(PhoneNumber::normalize($input))->toBe($expected);
})->with([
    ['09123456789', '+989123456789'],
    ['+989123456789', '+989123456789'],
    ['989123456789', '+989123456789'],
    ['۰۹۱۲۳۴۵۶۷۸۹', '+989123456789'],
    ['0912 345 6789', '+989123456789'],
    ['0912-345-6789', '+989123456789'],
]);

it('returns null for invalid numbers', function (string $input) {
    expect(PhoneNumber::normalize($input))->toBeNull();
})->with([
    ['123'],
    ['08123456789'],
    ['0912345678'],
    ['+1234567890'],
    [''],
    ['abcdefg'],
]);

it('formats phone for display', function () {
    expect(PhoneNumber::format('+989123456789'))->toBe('0912 345 6789');
});

it('converts persian and arabic digits to latin', function () {
    expect(PhoneNumber::toLatinDigits('۰۱۲۳۴۵۶۷۸۹'))->toBe('0123456789');
    expect(PhoneNumber::toLatinDigits('٠١٢٣٤٥٦٧٨٩'))->toBe('0123456789');
});

it('converts latin digits to persian', function () {
    expect(PhoneNumber::toPersianDigits('0123456789'))->toBe('۰۱۲۳۴۵۶۷۸۹');
});
