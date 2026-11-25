<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MatchStatus;
use App\Enums\ResultStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** بازی (جدول `matches`)؛ نام `Match` در PHP رزرو است. */
class SportMatch extends Model
{
    protected $table = 'matches';

    protected $fillable = [
        'stage_id', 'parent_match_id', 'group_name', 'round', 'leg', 'number', 'bracket',
        'a_id', 'b_id', 'a_source', 'b_source', 'next_match_id', 'next_slot',
        'loser_next_match_id', 'loser_next_slot', 'scheduled_at', 'court', 'status', 'winner_id',
        'score_a', 'score_b', 'points_a', 'points_b', 'result_status', 'counts_in_standings',
        'is_manual', 'result_meta', 'entered_by', 'confirmed_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => MatchStatus::class,
            'result_status' => ResultStatus::class,
            'a_source' => 'array',
            'b_source' => 'array',
            'result_meta' => 'array',
            'scheduled_at' => 'datetime',
            'counts_in_standings' => 'boolean',
            'is_manual' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SportMatch $match): void {
            $match->uuid ??= (string) Str::uuid();
        });
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    public function a(): BelongsTo
    {
        return $this->belongsTo(Competitor::class, 'a_id');
    }

    public function b(): BelongsTo
    {
        return $this->belongsTo(Competitor::class, 'b_id');
    }
}
