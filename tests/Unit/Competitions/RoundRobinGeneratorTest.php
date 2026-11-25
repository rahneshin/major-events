<?php

use App\Services\Competitions\Engine\RoundRobinGenerator;

it('builds a 15 team double round robin with 210 matches in 30 rounds', function () {
    $ids = range(1, 15);
    $plan = (new RoundRobinGenerator)->generate($ids, 2);
    $fixtures = $plan['fixtures'];

    expect($fixtures)->toHaveCount(210)
        ->and($plan['rounds'])->toBe(30);

    // هر دور: ۷ بازی و هر تیم حداکثر یک بار
    foreach (range(1, 30) as $round) {
        $inRound = array_values(array_filter($fixtures, fn ($f) => $f['round'] === $round));
        expect($inRound)->toHaveCount(7);

        $teams = [];
        foreach ($inRound as $f) {
            $teams[] = $f['a'];
            $teams[] = $f['b'];
        }
        expect(count(array_unique($teams)))->toBe(14);
    }

    // هر جفت مرتب (میزبان، مهمان) دقیقاً یک بار
    $pairs = array_map(fn ($f) => $f['a'].'-'.$f['b'], $fixtures);
    expect(count(array_unique($pairs)))->toBe(210);

    // هر تیم ۲۸ بازی
    foreach ($ids as $id) {
        $played = array_filter($fixtures, fn ($f) => $f['a'] === $id || $f['b'] === $id);
        expect($played)->toHaveCount(28);
    }

    // هر تیم در هر دور یک استراحت ← دو استراحت در کل
    $counts = array_count_values($plan['byes']);
    expect($counts)->toHaveCount(15);
    foreach ($counts as $c) {
        expect($c)->toBe(2);
    }
});

it('builds a 16 team single round robin without byes', function () {
    $plan = (new RoundRobinGenerator)->generate(range(1, 16), 1);

    expect($plan['fixtures'])->toHaveCount(120)
        ->and($plan['rounds'])->toBe(15)
        ->and($plan['byes'])->toBe([]);

    foreach (range(1, 15) as $round) {
        expect(array_filter($plan['fixtures'], fn ($f) => $f['round'] === $round))->toHaveCount(8);
    }
});

it('plays every unordered pair exactly once in a single leg', function () {
    $fixtures = (new RoundRobinGenerator)->generate(range(1, 7), 1)['fixtures'];

    $pairs = array_map(function ($f) {
        $p = [$f['a'], $f['b']];
        sort($p);

        return implode('-', $p);
    }, $fixtures);

    expect($fixtures)->toHaveCount(21)
        ->and(count(array_unique($pairs)))->toBe(21);
});

it('works for the smallest field of two', function () {
    $plan = (new RoundRobinGenerator)->generate([10, 20], 2);

    expect($plan['fixtures'])->toHaveCount(2)
        ->and($plan['fixtures'][0]['a'])->toBe($plan['fixtures'][1]['b']);
});

it('rejects invalid input', function () {
    $gen = new RoundRobinGenerator;

    expect(fn () => $gen->generate([1]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $gen->generate([1, 1, 2]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $gen->generate([1, 2, 3], 3))->toThrow(InvalidArgumentException::class);
});
