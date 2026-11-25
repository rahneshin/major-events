<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeliverableStatus;
use App\Enums\SponsorCategory;
use App\Enums\SponsorStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Sponsor extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'event_id',
        'package_id',
        'name',
        'category',
        'contact_name',
        'contact_phone',
        'status',
        'cash_value',
        'in_kind_value',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'category' => SponsorCategory::class,
            'status' => SponsorStatus::class,
            'cash_value' => 'integer',
            'in_kind_value' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Sponsor $sponsor) {
            if (empty($sponsor->uuid)) {
                $sponsor->uuid = (string) Str::uuid();
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

    public function package(): BelongsTo
    {
        return $this->belongsTo(SponsorshipPackage::class, 'package_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deliverables(): HasMany
    {
        return $this->hasMany(SponsorDeliverable::class);
    }

    // --------------------------------------------------------------- scopes

    public function scopeForEvent(Builder $query, Event $event): Builder
    {
        return $query->where('event_id', $event->id);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByDesc('id');
    }

    // -------------------------------------------------------------- helpers

    /** ارزش کل حمایت = نقدی + غیرنقدی (ریال). */
    public function totalValue(): int
    {
        return (int) $this->cash_value + (int) $this->in_kind_value;
    }

    public function hasOpenDeliverables(): bool
    {
        return $this->deliverables()
            ->whereIn('status', DeliverableStatus::openValues())
            ->exists();
    }
}
