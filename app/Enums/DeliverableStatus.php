<?php

declare(strict_types=1);

namespace App\Enums;

enum DeliverableStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('sponsorship.deliverable_status.'.$this->value);
    }

    /** هنوز کار دارد: برای «معوق» و «ممنوعیت تحویل اسپانسر» حساب می‌شود. */
    public function isOpen(): bool
    {
        return in_array($this, [self::Pending, self::InProgress], true);
    }

    /**
     * @return list<string>
     */
    public static function openValues(): array
    {
        return [self::Pending->value, self::InProgress->value];
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-ink-100 text-ink-700',
            self::InProgress => 'bg-flag-300/40 text-ink-800',
            self::Delivered => 'bg-success-50 text-success-700',
            self::Cancelled => 'bg-ink-50 text-ink-400',
        };
    }
}
