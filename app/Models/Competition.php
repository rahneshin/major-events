<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CompetitionGender;
use App\Enums\CompetitionStatus;
use App\Enums\ParticipantType;
use App\Enums\ResultStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Competition extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'event_id', 'parent_id', 'name', 'sport_code', 'gender', 'participant_type', 'result_type',
        'scoring_config', 'settings', 'status', 'requires_confirmation', 'capacity',
        'registration_opens_at', 'registration_closes_at', 'public_token',
    ];

    protected function casts(): array
    {
        return [
            'gender' => CompetitionGender::class,
            'participant_type' => ParticipantType::class,
            'status' => CompetitionStatus::class,
            'scoring_config' => 'array',
            'settings' => 'array',
            'requires_confirmation' => 'boolean',
            'capacity' => 'integer',
            'registration_opens_at' => 'datetime',
            'registration_closes_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Competition $competition): void {
            $competition->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function scopeForEvent(Builder $query, Event $event): Builder
    {
        return $query->where('event_id', $event->id);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function competitors(): HasMany
    {
        return $this->hasMany(Competitor::class);
    }

    public function stages(): HasMany
    {
        return $this->hasMany(Stage::class)->orderBy('position');
    }

    public function matches(): HasManyThrough
    {
        return $this->hasManyThrough(SportMatch::class, Stage::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'competition_user')->withPivot('role')->withTimestamps();
    }

    public function firstStage(): ?Stage
    {
        return $this->stages()->first();
    }

    /** بعد از اولین نتیجه‌ی تأییدشده، قرعه/جدول قفل می‌شود. */
    public function hasConfirmedResults(): bool
    {
        return $this->matches()
            ->where('matches.result_status', ResultStatus::Confirmed->value)
            ->exists();
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return data_get($this->scoring_config, $key, $default);
    }
}
