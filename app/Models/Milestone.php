<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MilestoneStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Milestone extends Model
{
    protected $fillable = [
        'event_id',
        'title',
        'description',
        'due_at',
        'status',
        'reached_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => MilestoneStatus::class,
            'due_at' => 'datetime',
            'reached_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByRaw('due_at is null')->orderBy('due_at')->orderBy('id');
    }

    public function isOverdue(): bool
    {
        return $this->status === MilestoneStatus::Pending
            && $this->due_at !== null
            && $this->due_at->isPast();
    }
}
