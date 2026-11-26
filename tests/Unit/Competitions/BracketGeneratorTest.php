<?php

use App\Services\Competitions\Engine\BracketGenerator;
use App\Services\Competitions\Engine\GroupSplitter;

it('orders seeds so that the top seeds meet only late', function () {
    $g = new BracketGenerator;

    expect($g->seedOrder(2))->toBe([1, 2])
        ->and($g->seedOrder(4))->toBe([1, 4, 2, 3])
        ->and($g->seedOrder(8))->toBe([1, 8, 4, 5, 2, 7, 3, 6]);
});

it('builds a full 8-entrant bracket without byes', function () {
    $plan = (new BracketGenerator)->generate(8);

    expect($plan['size'])->toBe(8)
        ->and($plan['rounds'])->toBe(3)
        ->and($plan['byes'])->toBe([])
        ->and($plan['matches'])->toHaveCount(7);

    $round1 = array_values(array_filter($plan['matches'], fn ($m) => $m['round'] === 1));
    expect(array_map(fn ($m) => [$m['a']['seed'], $m['b']['seed']], $round1))->toBe([[1, 8], [4, 5], [2, 7], [3, 6]]);

    $final = array_values(array_filter($plan['matches'], fn ($m) => $m['round'] === 3));
    expect($final)->toHaveCount(1)->and($final[0]['next'])->toBeNull();
});

it('gives byes to the top seeds and numbers round 1 without gaps', function () {
    $plan = (new BracketGenerator)->generate(6);

    expect($plan['size'])->toBe(8)
        ->and($plan['byes'])->toBe([1, 2]);

    $round1 = array_values(array_filter($plan['matches'], fn ($m) => $m['round'] === 1));
    expect($round1)->toHaveCount(2)
        ->and(array_column($round1, 'number'))->toBe([1, 2]);

    // دور دوم: بذر ۱ مستقیم می‌نشیند و حریفش برنده‌ی بازی ۱ دور ۱ است
    $r2 = array_values(array_filter($plan['matches'], fn ($m) => $m['round'] === 2));
    expect($r2[0]['a'])->toBe(['seed' => 1])
        ->and($r2[0]['b'])->toBe(['winner_of' => ['round' => 1, 'number' => 1]]);
});

it('supports two entrants and three entrants', function () {
    $two = (new BracketGenerator)->generate(2, true);
    expect($two['matches'])->toHaveCount(1)->and($two['third_place'])->toBeFalse();

    $three = (new BracketGenerator)->generate(3, true);
    expect($three['matches'])->toHaveCount(2)->and($three['third_place'])->toBeFalse()->and($three['byes'])->toBe([1]);
});

it('adds a third place match only for four or more entrants', function () {
    $plan = (new BracketGenerator)->generate(4, true);

    expect($plan['third_place'])->toBeTrue()->and($plan['matches'])->toHaveCount(4);

    $third = collect($plan['matches'])->firstWhere('bracket', 'third_place');
    expect($third['round'])->toBe(2)->and($third['number'])->toBe(2)
        ->and($third['a'])->toBe(['loser_of' => ['round' => 1, 'number' => 1]]);

    $semi = collect($plan['matches'])->first(fn ($m) => $m['round'] === 1 && $m['number'] === 2);
    expect($semi['loser_next'])->toBe(['round' => 2, 'number' => 2, 'slot' => 'b']);
});

it('rejects out-of-range entrant counts', function () {
    expect(fn () => (new BracketGenerator)->generate(1))->toThrow(InvalidArgumentException::class)
        ->and(fn () => (new BracketGenerator)->generate(257))->toThrow(InvalidArgumentException::class);
});

it('computes final ranks with shared ranks and a third place override', function () {
    $g = new BracketGenerator;

    $results = [
        ['round' => 1, 'bracket' => 'winners', 'winner' => 1, 'loser' => 8],
        ['round' => 1, 'bracket' => 'winners', 'winner' => 4, 'loser' => 5],
        ['round' => 2, 'bracket' => 'winners', 'winner' => 1, 'loser' => 4],
        ['round' => 3, 'bracket' => 'winners', 'winner' => 1, 'loser' => 2],
    ];
    $ranks = $g->ranks(8, $results);

    expect($ranks[1])->toBe(1)->and($ranks[2])->toBe(2)->and($ranks[4])->toBe(3)
        ->and($ranks[8])->toBe(5)->and($ranks[5])->toBe(5);

    $withThird = $g->ranks(8, array_merge($results, [
        ['round' => 3, 'bracket' => 'third_place', 'winner' => 4, 'loser' => 7],
    ]));
    expect($withThird[4])->toBe(3)->and($withThird[7])->toBe(4);
});

it('cross-seeds group qualifiers so same-group teams do not meet in round 1', function () {
    $g = new BracketGenerator;

    foreach ([2, 4, 8] as $groups) {
        $seeding = $g->qualifierSeeding($groups, 2);
        $plan = $g->generate($groups * 2);

        $round1 = array_filter($plan['matches'], fn ($m) => $m['round'] === 1);

        foreach ($round1 as $m) {
            $a = $seeding[$m['a']['seed'] - 1];
            $b = $seeding[$m['b']['seed'] - 1];
            expect($a['group'])->not->toBe($b['group']);
        }
    }

    // ۴ گروه: A1–B2، D1–C2، B1–A2، C1–D2
    $seeding = $g->qualifierSeeding(4, 2);
    expect(array_slice($seeding, 4))->toBe([
        ['group' => 2, 'rank' => 2], ['group' => 3, 'rank' => 2], ['group' => 0, 'rank' => 2], ['group' => 1, 'rank' => 2],
    ]);
});

it('splits groups by snake for seeded and round-robin deal for random', function () {
    $split = new GroupSplitter;
    $order = range(1, 8);

    expect($split->split($order, 2, 'seeded'))->toBe([[1, 4, 5, 8], [2, 3, 6, 7]])
        ->and($split->split($order, 2, 'random'))->toBe([[1, 3, 5, 7], [2, 4, 6, 8]])
        ->and($split->name(0))->toBe('A')->and($split->name(3))->toBe('D');
});

it('keeps group sizes within one and validates input', function () {
    $split = new GroupSplitter;

    $groups = $split->split(range(1, 10), 3, 'seeded');
    $sizes = array_map('count', $groups);
    expect(max($sizes) - min($sizes))->toBeLessThanOrEqual(1)->and(array_sum($sizes))->toBe(10);

    expect(fn () => $split->split(range(1, 5), 3, 'seeded'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $split->split(range(1, 8), 1, 'seeded'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $split->split(range(1, 8), 2, 'weird'))->toThrow(InvalidArgumentException::class);
});
