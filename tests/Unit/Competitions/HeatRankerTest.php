<?php

use App\Services\Competitions\Engine\HeatRanker;

function hr_rank(array $values, string $better = 'lower'): array
{
    $entries = [];
    foreach ($values as $id => $v) {
        $entries[] = is_array($v) ? ['id' => $id] + $v : ['id' => $id, 'value' => $v];
    }

    return (new HeatRanker)->rank($entries, $better);
}

it('ranks lower values first for times', function () {
    $r = hr_rank([1 => 10.5, 2 => 9.9, 3 => 11.0]);

    expect($r[2]['rank'])->toBe(1)->and($r[1]['rank'])->toBe(2)->and($r[3]['rank'])->toBe(3);
});

it('ranks higher values first for distances', function () {
    $r = hr_rank([1 => 6.1, 2 => 7.2, 3 => 5.0], 'higher');

    expect($r[2]['rank'])->toBe(1)->and($r[1]['rank'])->toBe(2)->and($r[3]['rank'])->toBe(3);
});

it('gives tied values a shared rank and skips the next rank', function () {
    $r = hr_rank([1 => 10.0, 2 => 10.5, 3 => 10.5, 4 => 11.0]);

    expect($r[1]['rank'])->toBe(1)
        ->and($r[2]['rank'])->toBe(2)->and($r[3]['rank'])->toBe(2)
        ->and($r[2]['tied'])->toBeTrue()->and($r[1]['tied'])->toBeFalse()
        ->and($r[4]['rank'])->toBe(4);
});

it('does not rank DNS, DNF, DQ or missing values', function () {
    $r = hr_rank([
        1 => ['value' => 10.0],
        2 => ['value' => null],
        3 => ['value' => 9.0, 'status' => 'DQ'],
        4 => ['value' => null, 'status' => 'DNS'],
        5 => ['value' => 12.0, 'status' => 'DNF'],
    ]);

    expect($r[1]['rank'])->toBe(1)
        ->and($r[2]['rank'])->toBeNull()->and($r[3]['rank'])->toBeNull()
        ->and($r[4]['rank'])->toBeNull()->and($r[5]['rank'])->toBeNull();
});

it('is independent of input order', function () {
    $a = hr_rank([1 => 3.0, 2 => 1.0, 3 => 2.0]);
    $b = hr_rank([3 => 2.0, 2 => 1.0, 1 => 3.0]);

    expect(array_map(fn ($x) => $x['rank'], $a))->toEqual(array_map(fn ($x) => $x['rank'], $b));
});

it('compares in thousandths so float noise does not break ties', function () {
    $r = hr_rank([1 => 0.1 + 0.2, 2 => 0.3]);

    expect($r[1]['rank'])->toBe(1)->and($r[2]['rank'])->toBe(1);
});

it('rejects an unknown direction', function () {
    (new HeatRanker)->rank([], 'sideways');
})->throws(InvalidArgumentException::class);
