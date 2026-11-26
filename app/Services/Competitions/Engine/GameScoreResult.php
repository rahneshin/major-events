<?php

declare(strict_types=1);

namespace App\Services\Competitions\Engine;

final readonly class GameScoreResult
{
    /**
     * @param  list<array{0: int, 1: int}>  $games  گیم‌های نرمال‌شده [امتیاز A، امتیاز B]
     */
    public function __construct(
        public bool $valid,
        public ?string $error,
        public ?string $winner,
        public int $gamesA,
        public int $gamesB,
        public int $pointsA,
        public int $pointsB,
        public array $games = [],
    ) {}

    public static function invalid(string $error): self
    {
        return new self(false, $error, null, 0, 0, 0, 0);
    }
}
