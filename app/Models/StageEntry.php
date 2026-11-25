<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StageEntry extends Model
{
    public $timestamps = false;

    protected $fillable = ['stage_id', 'competitor_id', 'group_name', 'seed', 'draw_position', 'final_rank'];

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    public function competitor(): BelongsTo
    {
        return $this->belongsTo(Competitor::class);
    }
}
