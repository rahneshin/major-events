<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingPlan extends Model
{
    protected $fillable = [
        'event_id',
        'target_segments',
        'positioning',
        'message',
        'channels',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'target_segments' => 'array',
            'channels' => 'array',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
