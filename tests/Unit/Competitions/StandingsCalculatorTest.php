<?php

use App\Services\Competitions\Engine\StandingsCalculator;

function sc_match(int $a, int $b, int $sa, int $sb, array $extra = []): array
{
    return ['a' => $a, 'b' => $b, 'score_a' => $sa, 'score_b' => $sb] + $extra;
}

function sc_order(array $rows): array
{
    return array_map(fn ($r) => $r['competitor_id'], $rows);
}

it('awards 3/1/0 points and breaks ties by goal difference', function () {
    $rows = (new StandingsCalculator)->calculate([1, 2, 3], [
        sc_match(1, 2, 2, 0),
        sc_match(1, 3, 1, 0),
        sc_match(2, 3, 1, 1),
    ], ['tiebreakers' => ['points', 'diff', 'for']]);

    expect(sc_order($rows))->toBe([1, 3, 2])
        ->and($rows[0]['points'])->toBe(6)
        ->and($rows[1]['points'])->toBe(1)
        ->and($rows[1]['diff'])->toBe(-1)
        ->and($rows[2]['diff'])->toBe(-2);
});

it('uses head to head before goal difference when configured', function () {
    $matches = [
        sc_match(1, 2, 1, 0),
        sc_match(1, 3, 0, 1),
        sc_match(1, 4, 0, 1),
        sc_match(2, 3, 9, 0),
        sc_match(2, 4, 0, 1),
        sc_match(3, 4, 0, 0),
    ];

    $withH2h = (new StandingsCalculator)->calculate([1, 2, 3, 4], $matches, [
        'tiebreakers' => ['points', 'head_to_head_points', 'diff'],
    ]);
    $withoutH2h = (new StandingsCalculator)->calculate([1, 2, 3, 4], $matches, [
        'tiebreakers' => ['points', 'diff'],
    ]);

    // ۱ و ۲ هر دو ۳ امتیاز دارند؛ ۱ در رودررو برده است، ۲ تفاضل بهتری دارد
    expect(sc_order($withH2h))->toBe([4, 3, 1, 2])
        ->and(sc_order($withoutH2h))->toBe([4, 3, 2, 1]);
});

it('is independent of the order of the input', function () {
    $matches = [
        sc_match(1, 2, 1, 0), sc_match(2, 3, 2, 2), sc_match(3, 1, 0, 3), sc_match(4, 1, 1, 1),
    ];
    $config = ['tiebreakers' => ['points', 'head_to_head_points', 'diff', 'for']];

    $a = (new StandingsCalculator)->calculate([1, 2, 3, 4], $matches, $config);
    $b = (new StandingsCalculator)->calculate([4, 3, 2, 1], array_reverse($matches), $config);

    expect($a)->toBe($b);
});

it('keeps genuinely tied teams on the same rank', function () {
    $rows = (new StandingsCalculator)->calculate([1, 2, 3], [sc_match(1, 2, 1, 1)], [
        'tiebreakers' => ['points', 'diff'],
    ]);

    // ۱ و ۲ مساوی؛ ۳ هیچ بازی ندارد و ۰ امتیاز
    expect($rows[0]['rank'])->toBe(1)
        ->and($rows[1]['rank'])->toBe(1)
        ->and($rows[0]['tied'])->toBeTrue()
        ->and($rows[2]['rank'])->toBe(3)
        ->and($rows[2]['tied'])->toBeFalse();
});

it('resolves remaining ties by lots only when a lots seed exists and reproducibly', function () {
    $config = ['tiebreakers' => ['points', 'draw_lots']];

    $without = (new StandingsCalculator)->calculate([1, 2, 3], [], $config);
    expect(array_unique(array_column($without, 'rank')))->toBe([1])
        ->and($without[0]['tied'])->toBeTrue();

    $a = (new StandingsCalculator)->calculate([1, 2, 3], [], $config + ['lots_seed' => 'xyz']);
    $b = (new StandingsCalculator)->calculate([3, 1, 2], [], $config + ['lots_seed' => 'xyz']);

    expect(array_column($a, 'rank'))->toBe([1, 2, 3])
        ->and(sc_order($a))->toBe(sc_order($b));
});

it('counts a walkover as a win without goals', function () {
    $rows = (new StandingsCalculator)->calculate([1, 2], [
        ['a' => 1, 'b' => 2, 'winner' => 2, 'walkover' => true],
    ]);

    expect($rows[0]['competitor_id'])->toBe(2)
        ->and($rows[0]['points'])->toBe(3)
        ->and($rows[0]['for'])->toBe(0)
        ->and($rows[1]['lost'])->toBe(1);
});

it('applies score based table points such as squash 3-2', function () {
    $config = ['table_points' => ['win' => 3, 'draw' => 0, 'loss' => 0, 'by_score' => ['3-2' => [2, 1]]]];

    $rows = (new StandingsCalculator)->calculate([1, 2], [sc_match(1, 2, 3, 2)], $config);

    expect($rows[0]['points'])->toBe(2)
        ->and($rows[1]['points'])->toBe(1);
});

it('ignores matches that involve teams outside the table', function () {
    $rows = (new StandingsCalculator)->calculate([1, 2], [sc_match(1, 9, 5, 0), sc_match(1, 2, 1, 0)]);

    expect($rows[0]['played'])->toBe(1);
});
