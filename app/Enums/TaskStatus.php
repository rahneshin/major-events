<?php

declare(strict_types=1);

namespace App\Enums;

enum TaskStatus: string
{
    case Todo = 'todo';
    case Doing = 'doing';
    case Blocked = 'blocked';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('task.status.'.$this->value);
    }

    /**
     * Still has work left (counts for "overdue").
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::Todo, self::Doing, self::Blocked], true);
    }

    public function isFinal(): bool
    {
        return ! $this->isOpen();
    }
}
