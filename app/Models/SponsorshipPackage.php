<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class SponsorshipPackage extends Model
{
    use SoftDeletes;

    protected $table = 'sponsorship_packages';

    protected $fillable = [
        'uuid',
        'event_id',
        'name',
        'description',
        'price',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SponsorshipPackage $package) {
            if (empty($package->uuid)) {
                $package->uuid = (string) Str::uuid();
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

    /**
     * دارایی‌های بسته (دارایی حذف‌شده‌ی soft از نتیجه کنار می‌رود).
     */
    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(SponsorshipAsset::class, 'sponsorship_package_asset', 'package_id', 'asset_id')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    public function sponsors(): HasMany
    {
        return $this->hasMany(Sponsor::class, 'package_id');
    }

    public function scopeForEvent(Builder $query, Event $event): Builder
    {
        return $query->where('event_id', $event->id);
    }

    /**
     * جمع قیمت پایه‌ی دارایی‌های بسته (قیمت هر واحد × تعداد در بسته) — برای مقایسه با قیمت بسته.
     */
    public function assetsValue(): int
    {
        return (int) $this->assets->sum(fn (SponsorshipAsset $a) => (int) $a->price * (int) $a->pivot->quantity);
    }
}
