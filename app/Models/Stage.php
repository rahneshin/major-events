<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StageFormat;
use App\Enums\StageStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stage extends Model
{
    protected $fillable = ['competition_id', 'position', 'name', 'format', 'config', 'is_final', 'status'];

    protected function casts(): array
    {
        return [
            'format' => StageFormat::class,
            'status' => StageStatus::class,
            'config' => 'array',
            'is_final' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(StageEntry::class)->orderBy('draw_position');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(SportMatch::class);
    }

    public function draws(): HasMany
    {
        return $this->hasMany(Draw::class);
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return data_get($this->config, $key, $default);
    }
}
