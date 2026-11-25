<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AssetType;
use App\Enums\DeliverableStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class SponsorshipAsset extends Model
{
    use SoftDeletes;

    protected $table = 'sponsorship_assets';

    protected $fillable = [
        'uuid',
        'event_id',
        'name',
        'type',
        'inventory',
        'price',
        'description',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => AssetType::class,
            'inventory' => 'integer',
            'price' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SponsorshipAsset $asset) {
            if (empty($asset->uuid)) {
                $asset->uuid = (string) Str::uuid();
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

    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(SponsorshipPackage::class, 'sponsorship_package_asset', 'asset_id', 'package_id')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    public function deliverables(): HasMany
    {
        return $this->hasMany(SponsorDeliverable::class, 'asset_id');
    }

    public function scopeForEvent(Builder $query, Event $event): Builder
    {
        return $query->where('event_id', $event->id);
    }

    /**
     * واحدهای تخصیص‌یافته: مجموع تعداد تعهدهای فعال (باز یا تحویل‌شده؛ لغوشده حساب نمی‌شود).
     */
    public function allocatedQuantity(): int
    {
        return (int) $this->deliverables()
            ->where('status', '!=', DeliverableStatus::Cancelled->value)
            ->sum('quantity');
    }

    public function remainingQuantity(): int
    {
        return max(0, (int) $this->inventory - $this->allocatedQuantity());
    }
}
