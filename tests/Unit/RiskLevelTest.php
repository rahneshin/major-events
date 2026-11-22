<?php

use App\Enums\RiskLevel;

it('maps the score to a level', function (int $score, RiskLevel $level) {
    expect(RiskLevel::fromScore($score))->toBe($level);
})->with([
    [1, RiskLevel::Low],
    [4, RiskLevel::Low],
    [5, RiskLevel::Medium],
    [9, RiskLevel::Medium],
    [10, RiskLevel::High],
    [14, RiskLevel::High],
    [15, RiskLevel::Critical],
    [25, RiskLevel::Critical],
]);
