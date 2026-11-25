<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CompetitorStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Competitor extends Model
{
    protected $fillable = [
        'competition_id', 'name', 'affiliation', 'seed', 'status', 'documents', 'final_rank', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'status' => CompetitorStatus::class,
            'documents' => 'array',
            'meta' => 'array',
            'seed' => 'integer',
            'final_rank' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Competitor $competitor): void {
            $competitor->uuid ??= (string) Str::uuid();
        });
    }

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(CompetitorMember::class);
    }

    public function isActive(): bool
    {
        return $this->status !== CompetitorStatus::Withdrawn;
    }
}
