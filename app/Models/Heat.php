<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ResultStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** سری (ورزش رتبه‌ای): چند نفر هم‌زمان، نتیجه یک عدد و رتبه. */
class Heat extends Model
{
    protected $fillable = ['stage_id', 'name', 'position', 'scheduled_at', 'result_status', 'entered_by', 'confirmed_by'];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'scheduled_at' => 'datetime',
            'result_status' => ResultStatus::class,
        ];
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(HeatResult::class)->orderBy('lane')->orderBy('id');
    }

    public function isConfirmed(): bool
    {
        return $this->result_status === ResultStatus::Confirmed;
    }
}
