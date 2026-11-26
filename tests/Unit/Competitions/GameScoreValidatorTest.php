<?php

use App\Services\Competitions\Engine\GameScoreValidator;

function gv(array $games, array $rules = []): object
{
    return (new GameScoreValidator)->validate($games, $rules);
}

it('accepts a straight 3-0 win and sums the points', function () {
    $r = gv([[11, 5], [11, 7], [11, 9]]);

    expect($r->valid)->toBeTrue()
        ->and($r->winner)->toBe('a')
        ->and([$r->gamesA, $r->gamesB])->toBe([3, 0])
        ->and([$r->pointsA, $r->pointsB])->toBe([33, 21]);
});

it('accepts a 3-2 win with a deuce game', function () {
    $r = gv([[11, 9], [9, 11], [11, 9], [8, 11], [12, 10]]);

    expect($r->valid)->toBeTrue()
        ->and([$r->gamesA, $r->gamesB])->toBe([3, 2])
        ->and($r->winner)->toBe('a');
});

it('lets b win and normalises string input', function () {
    $r = gv([['5', '11'], ['3', '11'], ['9', '11']]);

    expect($r->valid)->toBeTrue()->and($r->winner)->toBe('b')->and($r->games[0])->toBe([5, 11]);
});

it('rejects games that break the points and win-by rules', function (array $game) {
    expect(gv([$game, [11, 1], [11, 1]])->error)->toBe('game_invalid');
})->with([
    'win by one' => [[11, 10]],
    'below the target' => [[10, 8]],
    'tie' => [[11, 11]],
    'too far after deuce' => [[13, 10]],
    'deuce lead of three' => [[14, 11]],
]);

it('rejects a match that is not finished', function () {
    expect(gv([[11, 5], [11, 5]])->error)->toBe('match_incomplete')
        ->and(gv([])->error)->toBe('match_incomplete');
});

it('rejects extra games after the match is decided', function () {
    expect(gv([[11, 5], [11, 5], [11, 5], [11, 5]])->error)->toBe('game_after_end');
});

it('rejects non numeric or negative scores', function () {
    expect(gv([['x', 5]])->error)->toBe('game_invalid')
        ->and(gv([[-1, 5]])->error)->toBe('game_invalid')
        ->and(gv([[11]])->error)->toBe('game_invalid');
});

it('supports best of three and custom targets with a cap', function () {
    $rules = ['best_of' => 3, 'points_per_game' => 21, 'win_by' => 2, 'max_points' => 30];

    expect(gv([[21, 19], [30, 29]], $rules)->valid)->toBeTrue()
        ->and(gv([[22, 20], [21, 10]], $rules)->valid)->toBeTrue()
        ->and(gv([[30, 28], [21, 10]], $rules)->error)->toBe('game_invalid')
        ->and(gv([[23, 20], [21, 10]], $rules)->error)->toBe('game_invalid')
        ->and(gv([[31, 29], [21, 10]], $rules)->error)->toBe('game_invalid');
});

it('refuses an even best-of', function () {
    expect(fn () => gv([[11, 5]], ['best_of' => 4]))->toThrow(InvalidArgumentException::class);
});
