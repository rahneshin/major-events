<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * prospect → negotiating → signed → delivered، و lost در هر مرحله‌ی پیش از تحویل.
 * «delivered» فقط وقتی مجاز است که هیچ تعهد بازی نمانده باشد (قاعده در SponsorshipService).
 */
enum SponsorStatus: string
{
    case Prospect = 'prospect';
    case Negotiating = 'negotiating';
    case Signed = 'signed';
    case Delivered = 'delivered';
    case Lost = 'lost';

    public function label(): string
    {
        return __('sponsorship.status.'.$this->value);
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Prospect => [self::Negotiating, self::Signed, self::Lost],
            self::Negotiating => [self::Prospect, self::Signed, self::Lost],
            self::Signed => [self::Delivered, self::Negotiating, self::Lost],
            self::Delivered => [self::Signed],
            self::Lost => [self::Prospect, self::Negotiating],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** قرارداد بسته شده (signed یا delivered): در مجموع «متعهد» حساب می‌شود. */
    public function isCommitted(): bool
    {
        return in_array($this, [self::Signed, self::Delivered], true);
    }

    /** هنوز در حال جذب (prospect یا negotiating): در مجموع «در جریان» حساب می‌شود. */
    public function isPipeline(): bool
    {
        return in_array($this, [self::Prospect, self::Negotiating], true);
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Prospect => 'bg-ink-100 text-ink-700',
            self::Negotiating => 'bg-flag-300/40 text-ink-800',
            self::Signed => 'bg-pool-100 text-pool-800',
            self::Delivered => 'bg-success-50 text-success-700',
            self::Lost => 'bg-signal-50 text-signal-700',
        };
    }
}
