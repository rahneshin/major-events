<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GoalKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventGoal extends Model
{
    protected $fillable = [
        'event_id',
        'kind',
        'title',
        'description',
        'priority',
        'smart_measure',
        'target_value',
        'unit',
    ];

    protected function casts(): array
    {
        return [
            'kind' => GoalKind::class,
            'priority' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
