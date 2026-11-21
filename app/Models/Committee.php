<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommitteeRole;
use App\Enums\CommitteeType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Committee extends Model
{
    protected $fillable = [
        'event_id',
        'name',
        'type',
        'description',
        'head_user_id',
        'color',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'type' => CommitteeType::class,
            'position' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function head(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_user_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'committee_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function heads(): BelongsToMany
    {
        return $this->members()->wherePivot('role', CommitteeRole::Head->value);
    }

    public function hasMember(User $user): bool
    {
        return $this->members()->where('user_id', $user->id)->exists();
    }

    public function isHead(User $user): bool
    {
        return (int) $this->head_user_id === (int) $user->id;
    }
}
