<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeliverableStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class SponsorDeliverable extends Model
{
    use SoftDeletes;

    protected $table = 'sponsor_deliverables';

    protected $fillable = [
        'uuid',
        'event_id',
        'sponsor_id',
        'asset_id',
        'task_id',
        'commitment',
        'quantity',
        'due_at',
        'status',
        'delivered_at',
        'evidence_path',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => DeliverableStatus::class,
            'quantity' => 'integer',
            'due_at' => 'date',
            'delivered_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SponsorDeliverable $deliverable) {
            if (empty($deliverable->uuid)) {
                $deliverable->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    // ------------------------------------------------------------ relations

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Sponsor::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(SponsorshipAsset::class, 'asset_id');
    }

    /**
     * وظیفه‌ی روز رویداد (یا هر وظیفه‌ی همین رویداد). وظیفه‌ی soft-delete‌شده null می‌شود.
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    // --------------------------------------------------------------- scopes

    public function scopeForEvent(Builder $query, Event $event): Builder
    {
        return $query->where('event_id', $event->id);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', DeliverableStatus::openValues());
    }

    /** موعد گذشته و هنوز باز. */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->open()
            ->whereNotNull('due_at')
            ->where('due_at', '<', now()->startOfDay()->toDateString());
    }

    // -------------------------------------------------------------- helpers

    public function isOverdue(): bool
    {
        return $this->due_at !== null
            && ($this->status?->isOpen() ?? false)
            && $this->due_at->lt(now()->startOfDay());
    }

    /** موعد از امروز تا N روز آینده (تعهد باز). */
    public function isDueSoon(int $days): bool
    {
        if ($this->due_at === null || ! ($this->status?->isOpen() ?? false)) {
            return false;
        }

        $today = now()->startOfDay();

        return $this->due_at->gte($today) && $this->due_at->lte($today->copy()->addDays($days));
    }
}
