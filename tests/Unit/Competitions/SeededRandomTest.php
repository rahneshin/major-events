<?php

use App\Services\Competitions\Engine\SeededRandom;

it('produces the same sequence for the same seed', function () {
    $a = new SeededRandom('seed-1');
    $b = new SeededRandom('seed-1');

    $seqA = array_map(fn () => $a->nextInt(0, 1000), range(1, 50));
    $seqB = array_map(fn () => $b->nextInt(0, 1000), range(1, 50));

    expect($seqA)->toBe($seqB);
});

it('produces different shuffles for different seeds', function () {
    $items = range(1, 30);

    expect((new SeededRandom('one'))->shuffle($items))
        ->not->toBe((new SeededRandom('two'))->shuffle($items));
});

it('shuffles without losing or duplicating items and keeps the input intact', function () {
    $items = range(1, 40);
    $shuffled = (new SeededRandom('abc'))->shuffle($items);

    expect($items)->toBe(range(1, 40));

    $sorted = $shuffled;
    sort($sorted);
    expect($sorted)->toBe($items);
});

it('keeps nextInt inside the requested bounds', function () {
    $rng = new SeededRandom('bounds');

    foreach (range(1, 300) as $_) {
        $v = $rng->nextInt(3, 9);
        expect($v)->toBeGreaterThanOrEqual(3)->toBeLessThanOrEqual(9);
    }

    expect($rng->nextInt(5, 5))->toBe(5);
});

it('generates a 16 character hex seed', function () {
    expect(SeededRandom::newSeed())->toMatch('/^[0-9a-f]{16}$/');
});
