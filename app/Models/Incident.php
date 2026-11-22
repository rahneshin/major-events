<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\IncidentSeverity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Incident extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'event_id',
        'risk_id',
        'title',
        'description',
        'severity',
        'reported_by',
        'occurred_at',
        'resolution',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'severity' => IncidentSeverity::class,
            'occurred_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Incident $incident) {
            if (empty($incident->uuid)) {
                $incident->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function risk(): BelongsTo
    {
        return $this->belongsTo(Risk::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function scopeForEvent(Builder $query, Event $event): Builder
    {
        return $query->where('event_id', $event->id);
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }
}
