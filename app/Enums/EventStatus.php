<?php

declare(strict_types=1);

namespace App\Enums;

enum EventStatus: string
{
    case Draft = 'draft';
    case Planning = 'planning';
    case Ready = 'ready';
    case Running = 'running';
    case Completed = 'completed';
    case Archived = 'archived';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'پیش‌نویس',
            self::Planning => 'در حال برنامه‌ریزی',
            self::Ready => 'آماده اجرا',
            self::Running => 'در حال اجرا',
            self::Completed => 'تکمیل‌شده',
            self::Archived => 'آرشیو',
            self::Cancelled => 'لغو شده',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [
            self::Draft,
            self::Planning,
            self::Ready,
            self::Running,
        ], true);
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Planning, self::Cancelled],
            self::Planning => [self::Ready, self::Draft, self::Cancelled],
            self::Ready => [self::Running, self::Planning, self::Cancelled],
            self::Running => [self::Completed, self::Cancelled],
            self::Completed => [self::Archived],
            self::Archived, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
