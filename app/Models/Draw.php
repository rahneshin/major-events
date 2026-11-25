<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** سند قرعه‌کشی: روش، seed و نتیجه — قابل ممیزی و بازتولید. */
class Draw extends Model
{
    protected $fillable = ['stage_id', 'method', 'rng_seed', 'constraints', 'result', 'performed_by'];

    protected function casts(): array
    {
        return ['constraints' => 'array', 'result' => 'array'];
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
