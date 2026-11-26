<?php

use App\Services\Competitions\Engine\HeatAdvancer;

/** @param list<list<float|null|array>> $heats مقدار یا ['value'=>,'status'=>]؛ شناسه‌ها پشت‌سرهم داده می‌شوند. */
function ha_heats(array $heats): array
{
    $id = 0;
    $out = [];
    foreach ($heats as $heat) {
        $rows = [];
        foreach ($heat as $v) {
            $id++;
            $rows[] = is_array($v) ? ['id' => $id] + $v : ['id' => $id, 'value' => $v];
        }
        $out[] = $rows;
    }

    return $out;
}

it('takes the first n of every heat plus the best remaining overall', function () {
    // سری ۱: ۱..۴ | سری ۲: ۵..۸
    $heats = ha_heats([[10.0, 10.2, 10.4, 10.6], [10.1, 10.3, 10.5, 10.7]]);

    $r = (new HeatAdvancer)->select($heats, 2, 2, 'lower');

    // بهترین دو نفر هر سری: 1,2 و 5,6 ؛ بهترین دو نفر باقی‌مانده: 3 (10.4) و 7 (10.5)... مقدار 10.4 -> id3، 10.5 -> id7
    expect($r['by_rank'])->toEqualCanonicalizing([1, 2, 5, 6])
        ->and($r['by_time'])->toEqualCanonicalizing([3, 7])
        ->and($r['tied_at_cutoff'])->toBeFalse()
        ->and($r['advancers'])->toBe([1, 5, 2, 6, 3, 7]);
});

it('lets everyone tied at the per-heat cutoff advance', function () {
    $heats = ha_heats([[10.0, 10.5, 10.5, 11.0]]);

    $r = (new HeatAdvancer)->select($heats, 2, 0, 'lower');

    expect($r['advancers'])->toEqualCanonicalizing([1, 2, 3]);
});

it('includes all tied at the best-remaining cutoff and reports it', function () {
    $heats = ha_heats([[10.0, 10.2, 10.4], [10.1, 10.2, 10.6]]);

    // n=1 -> {1 (10.0), 4 (10.1)}؛ باقی‌مانده: 2(10.2),5(10.2),3(10.4),6(10.6)؛ بهترین ۱ نفر = تساوی 2 و 5
    $r = (new HeatAdvancer)->select($heats, 1, 1, 'lower');

    expect($r['by_time'])->toEqualCanonicalizing([2, 5])
        ->and($r['tied_at_cutoff'])->toBeTrue();
});

it('never advances DQ, DNF, DNS or missing results', function () {
    $heats = ha_heats([[
        ['value' => 9.0, 'status' => 'DQ'],
        ['value' => 10.0],
        ['value' => null, 'status' => 'DNS'],
        ['value' => null],
    ]]);

    $r = (new HeatAdvancer)->select($heats, 3, 3, 'lower');

    expect($r['advancers'])->toBe([2]);
});

it('works with higher-is-better', function () {
    $heats = ha_heats([[7.0, 6.0, 5.0], [6.5, 5.5, 4.0]]);

    $r = (new HeatAdvancer)->select($heats, 1, 1, 'higher');

    expect($r['by_rank'])->toEqualCanonicalizing([1, 4])->and($r['by_time'])->toBe([2]);
});

it('returns nothing when no places are configured', function () {
    $r = (new HeatAdvancer)->select(ha_heats([[10.0, 11.0]]), 0, 0, 'lower');

    expect($r['advancers'])->toBe([]);
});
