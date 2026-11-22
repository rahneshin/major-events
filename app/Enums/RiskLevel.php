<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Level derived from the score (likelihood × impact, 1–25):
 *  1–4 low · 5–9 medium · 10–14 high · 15–25 critical.
 */
enum RiskLevel: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    public const MIN_SCORE = 1;

    public const MAX_SCORE = 25;

    public static function fromScore(int $score): self
    {
        return match (true) {
            $score >= 15 => self::Critical,
            $score >= 10 => self::High,
            $score >= 5 => self::Medium,
            default => self::Low,
        };
    }

    public function label(): string
    {
        return __('risk.level.'.$this->value);
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Low => 'bg-pool-50 text-pool-700',
            self::Medium => 'bg-flag-300/40 text-ink-800',
            self::High => 'bg-flag-500 text-ink-950',
            self::Critical => 'bg-signal-500 text-white',
        };
    }

    public function cellClasses(): string
    {
        return match ($this) {
            self::Low => 'bg-pool-50 text-pool-800',
            self::Medium => 'bg-flag-300/40 text-ink-800',
            self::High => 'bg-flag-500/80 text-ink-950',
            self::Critical => 'bg-signal-500 text-white',
        };
    }
}
