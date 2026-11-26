<?php

use App\Support\HeatValue;
use App\Support\NameMasker;

it('parses plain numbers including Persian digits and separators', function () {
    expect(HeatValue::parse('10.23'))->toBe(10.23)
        ->and(HeatValue::parse('۱۰٫۲۳'))->toBe(10.23)
        ->and(HeatValue::parse('12,5'))->toBe(12.5)
        ->and(HeatValue::parse('  7 '))->toBe(7.0)
        ->and(HeatValue::parse(''))->toBeNull()
        ->and(HeatValue::parse(null))->toBeNull();
});

it('parses mm:ss.xx and h:mm:ss times into seconds', function () {
    expect(HeatValue::parse('1:23.45'))->toBe(83.45)
        ->and(HeatValue::parse('۱:۲۳٫۴۵'))->toBe(83.45)
        ->and(HeatValue::parse('1:05:30'))->toBe(3930.0);
});

it('rejects invalid values', function (string $bad) {
    HeatValue::parse($bad);
})->throws(InvalidArgumentException::class)->with(['abc', '1:75', '-5', '1:2:3:4', '10..5', '1e3']);

it('formats values for display', function () {
    expect(HeatValue::format(null))->toBe('—')
        ->and(HeatValue::format(10.5))->toBe('10.5')
        ->and(HeatValue::format(10.0))->toBe('10')
        ->and(HeatValue::format(100.0, 'distance'))->toBe('100')
        ->and(HeatValue::format(83.45))->toBe('1:23.45')
        ->and(HeatValue::format(120.0))->toBe('2:00')
        ->and(HeatValue::format(3930.0))->toBe('1:05:30');
});

it('abbreviates names to initials', function () {
    expect(NameMasker::abbreviate('علی رضایی'))->toBe('ع. ر.')
        ->and(NameMasker::abbreviate('  سارا  '))->toBe('س.')
        ->and(NameMasker::abbreviate("محمد\u{200C}حسین کریمی"))->toBe('م. ح. ک.')
        ->and(NameMasker::abbreviate(''))->toBe('');
});
