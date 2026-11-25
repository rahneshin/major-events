<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetitorMember extends Model
{
    protected $fillable = ['competitor_id', 'user_id', 'full_name', 'role'];

    public function competitor(): BelongsTo
    {
        return $this->belongsTo(Competitor::class);
    }
}
