<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventInvitation extends Model
{
    protected $fillable = [
        'event_id',
        'phone',
        'role',
        'token',
        'invited_by',
        'expires_at',
        'accepted_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'role' => EventRole::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending' && $this->expires_at->isFuture();
    }
}
