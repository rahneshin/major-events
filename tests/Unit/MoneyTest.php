<?php

use App\Support\Money;

it('parses user input into integer rial', function (string $input, ?int $expected) {
    expect(Money::parse($input, 'rial'))->toBe($expected);
})->with([
    'latin with commas' => ['1,200', 1200],
    'persian digits' => ['۱۲۰۰', 1200],
    'arabic digits' => ['١٢٠٠', 1200],
    'persian thousand separator' => ['۱٬۲۰۰', 1200],
    'spaces' => ['  ۱۲ ۰۰۰ ', 12000],
    'zero' => ['0', 0],
    'empty' => ['', null],
    'text' => ['abc', null],
    'negative' => ['-5', null],
    'decimal' => ['1.5', null],
    'persian decimal' => ['۱٫۵', null],
    'too many digits' => ['12345678901234567', null],
]);

it('converts toman input to rial (x10)', function () {
    expect(Money::parse('1,200', 'toman'))->toBe(12000)
        ->and(Money::parse('۱۲۰', Money::TOMAN))->toBe(1200)
        ->and(Money::parse('1.5', 'toman'))->toBeNull();
});

it('formats rial with persian digits and separators', function () {
    expect(Money::format(1234567))->toBe('۱٬۲۳۴٬۵۶۷ ریال')
        ->and(Money::format(0))->toBe('۰ ریال')
        ->and(Money::format(1234567, 'rial', false))->toBe('۱٬۲۳۴٬۵۶۷')
        ->and(Money::format(-5000))->toBe('-۵٬۰۰۰ ریال');
});

it('formats toman as rial divided by ten', function () {
    expect(Money::format(12000, 'toman'))->toBe('۱٬۲۰۰ تومان')
        ->and(Money::format(15, 'toman'))->toBe('۱٫۵ تومان')
        ->and(Money::format(-12000, 'toman', false))->toBe('-۱٬۲۰۰');
});

it('falls back to rial for unknown units', function () {
    expect(Money::normalizeUnit('dollar'))->toBe('rial')
        ->and(Money::normalizeUnit(null))->toBe('rial')
        ->and(Money::unitLabel('toman'))->toBe('تومان');
});
