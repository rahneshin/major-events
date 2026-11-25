<?php

use App\Services\Competitions\Engine\DrawEngine;

function de_items(): array
{
    return array_map(fn ($i) => ['id' => $i, 'seed' => null], range(1, 12));
}

it('reproduces a random draw from its seed regardless of input order', function () {
    $engine = new DrawEngine;
    $items = de_items();

    $first = $engine->draw($items, 'random', 'fixed-seed');
    $second = $engine->draw(array_reverse($items), 'random', 'fixed-seed');

    expect($first['order'])->toBe($second['order'])
        ->and($first['seed'])->toBe('fixed-seed');

    $sorted = $first['order'];
    sort($sorted);
    expect($sorted)->toBe(range(1, 12));
});

it('generates a seed when none is given', function () {
    $result = (new DrawEngine)->draw(de_items(), 'random');

    expect($result['seed'])->toMatch('/^[0-9a-f]{16}$/');
});

it('orders a seeded draw by seed with unseeded last', function () {
    $items = [
        ['id' => 1, 'seed' => null],
        ['id' => 2, 'seed' => 2],
        ['id' => 3, 'seed' => 1],
        ['id' => 4, 'seed' => null],
    ];

    expect((new DrawEngine)->draw($items, 'seeded')['order'])->toBe([3, 2, 1, 4]);
});

it('rejects an unknown method', function () {
    expect(fn () => (new DrawEngine)->draw(de_items(), 'magic'))->toThrow(InvalidArgumentException::class);
});
