<?php

declare(strict_types=1);

namespace App\Services\Competitions\Engine;

/**
 * اعتبارسنجی گیم‌به‌گیم (اسکواش، تنیس روی میز، بدمینتون، والیبال...).
 *
 * قواعد: برنده‌ی هر گیم باید به `points_per_game` برسد و حداقل `win_by` امتیاز جلو باشد؛
 * بعد از مرز، اختلاف دقیقاً `win_by` است (مگر سقف `max_points`)؛ بازی با رسیدن یک طرف به
 * (best_of÷۲ + ۱) گیم تمام می‌شود و گیم بعد از پایان یا ناقص‌ماندن بازی نامعتبر است.
 * کدهای خطا کلیدهای `competition.errors.*` هستند.
 */
final class GameScoreValidator
{
    /**
     * @param  list<array{0: mixed, 1: mixed}>  $games
     * @param  array{best_of?: int, points_per_game?: int, win_by?: int, max_points?: int|null}  $rules
     */
    public function validate(array $games, array $rules = []): GameScoreResult
    {
        $bestOf = (int) ($rules['best_of'] ?? 5);
        $ppg = (int) ($rules['points_per_game'] ?? 11);
        $winBy = max(1, (int) ($rules['win_by'] ?? 2));
        $max = isset($rules['max_points']) ? (int) $rules['max_points'] : null;

        if ($bestOf < 1 || $bestOf % 2 === 0) {
            throw new \InvalidArgumentException('best_of must be a positive odd number.');
        }

        $need = intdiv($bestOf, 2) + 1;
        $wa = $wb = $pa = $pb = 0;
        $normalized = [];

        foreach (array_values($games) as $game) {
            if ($wa === $need || $wb === $need) {
                return GameScoreResult::invalid('game_after_end');
            }

            if (! isset($game[0], $game[1]) || ! $this->isScore($game[0]) || ! $this->isScore($game[1])) {
                return GameScoreResult::invalid('game_invalid');
            }

            $a = (int) $game[0];
            $b = (int) $game[1];

            if (! $this->validGame($a, $b, $ppg, $winBy, $max)) {
                return GameScoreResult::invalid('game_invalid');
            }

            $a > $b ? $wa++ : $wb++;
            $pa += $a;
            $pb += $b;
            $normalized[] = [$a, $b];
        }

        if ($wa !== $need && $wb !== $need) {
            return GameScoreResult::invalid('match_incomplete');
        }

        return new GameScoreResult(true, null, $wa > $wb ? 'a' : 'b', $wa, $wb, $pa, $pb, $normalized);
    }

    private function isScore(mixed $value): bool
    {
        return (is_int($value) && $value >= 0) || (is_string($value) && preg_match('/^\d{1,3}$/', trim($value)) === 1);
    }

    private function validGame(int $a, int $b, int $ppg, int $winBy, ?int $max): bool
    {
        $hi = max($a, $b);
        $lo = min($a, $b);

        if ($hi === $lo || $hi < $ppg) {
            return false;
        }

        if ($max !== null && $hi > $max) {
            return false;
        }

        if ($max !== null && $hi === $max && $hi > $ppg) {
            return $lo === $hi - 1;
        }

        if ($hi === $ppg) {
            return $lo <= $ppg - $winBy;
        }

        return $hi - $lo === $winBy;
    }
}
