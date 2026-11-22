<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommitteeRole;
use App\Enums\CommitteeType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Committee extends Model
{
    use SoftDeletes;

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

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('id');
    }

    public function hasMember(User $user): bool
    {
        return $this->members()->where('users.id', $user->id)->exists();
    }

    public function isHead(User $user): bool
    {
        return $this->head_user_id !== null && (int) $this->head_user_id === (int) $user->id;
    }

    public function roleOf(User $user): ?CommitteeRole
    {
        $value = $this->members()->where('users.id', $user->id)->first()?->pivot->role;

        return $value ? CommitteeRole::tryFrom($value) : null;
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Decision (Step 07): a committee that still owns tasks cannot be deleted;
     * tasks must be moved to another committee first (never left orphaned).
     * Wired to the real tasks table in Step 08a (soft-deleted tasks do not count).
     */
    public function hasTasks(): bool
    {
        return $this->tasks()->exists();
    }
}
