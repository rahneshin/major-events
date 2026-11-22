<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RiskCategory;
use App\Enums\RiskLevel;
use App\Enums\RiskResponse;
use App\Enums\RiskStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Risk extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'event_id',
        'title',
        'category',
        'description',
        'likelihood',
        'impact',
        'score',
        'response',
        'control_actions',
        'owner_id',
        'status',
        'review_date',
        'template_key',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'category' => RiskCategory::class,
            'response' => RiskResponse::class,
            'status' => RiskStatus::class,
            'likelihood' => 'integer',
            'impact' => 'integer',
            'score' => 'integer',
            'review_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Risk $risk) {
            if (empty($risk->uuid)) {
                $risk->uuid = (string) Str::uuid();
            }
        });

        // score is always derived: nobody can store an inconsistent value
        static::saving(function (Risk $risk) {
            $risk->score = (int) $risk->likelihood * (int) $risk->impact;
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

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Control tasks linked to this risk (soft-deleted tasks are hidden).
     */
    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'risk_task')->withTimestamps();
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    // --------------------------------------------------------------- scopes

    public function scopeForEvent(Builder $query, Event $event): Builder
    {
        return $query->where('event_id', $event->id);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', RiskStatus::activeValues());
    }

    public function scopeByScore(Builder $query): Builder
    {
        return $query->orderByDesc('score')->orderByDesc('impact')->orderBy('id');
    }

    // -------------------------------------------------------------- helpers

    public function level(): RiskLevel
    {
        return RiskLevel::fromScore((int) $this->score);
    }

    /**
     * Review date passed and the risk is still being managed.
     */
    public function isReviewOverdue(): bool
    {
        return $this->review_date !== null
            && ($this->status?->isActive() ?? false)
            && $this->review_date->lt(now()->startOfDay());
    }
}
