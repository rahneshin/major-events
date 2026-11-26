<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HeatResultStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeatResult extends Model
{
    public $timestamps = false;

    protected $fillable = ['heat_id', 'competitor_id', 'lane', 'value', 'rank', 'status', 'advanced'];

    protected function casts(): array
    {
        return [
            'lane' => 'integer',
            'value' => 'float',
            'rank' => 'integer',
            'status' => HeatResultStatus::class,
            'advanced' => 'boolean',
        ];
    }

    public function heat(): BelongsTo
    {
        return $this->belongsTo(Heat::class);
    }

    public function competitor(): BelongsTo
    {
        return $this->belongsTo(Competitor::class);
    }
}
