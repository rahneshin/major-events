<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventRole;
use App\Enums\EventScale;
use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Enums\MainGoalType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Event extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'organization_id',
        'created_by',
        'title',
        'slug',
        'sport_type',
        'event_type',
        'scale',
        'status',
        'start_date',
        'end_date',
        'venue_name',
        'city',
        'province',
        'expected_participants',
        'expected_spectators',
        'budget_estimate',
        'main_goal_type',
        'description',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => EventType::class,
            'scale' => EventScale::class,
            'status' => EventStatus::class,
            'main_goal_type' => MainGoalType::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'budget_estimate' => 'integer',
            'expected_participants' => 'integer',
            'expected_spectators' => 'integer',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Event $event) {
            if (empty($event->uuid)) {
                $event->uuid = (string) Str::uuid();
            }
            if (empty($event->slug)) {
                $event->slug = static::uniqueSlug($event->title, $event->organization_id);
            }
        });

        static::created(function (Event $event) {
            // Creator becomes event_owner member
            $event->members()->attach($event->created_by, [
                'role' => EventRole::EventOwner->value,
                'added_by' => $event->created_by,
                'joined_at' => now(),
            ]);
        });

        // Keep events.created_by in sync when ownership role moves between members.
        static::updating(function (Event $event) {
            if (! $event->wasChanged('created_by')) {
                return;
            }

            $event->members()->updateExistingPivot((int) $event->getOriginal('created_by'), [
                'role' => EventRole::Member->value,
            ]);
            $event->members()->updateExistingPivot((int) $event->created_by, [
                'role' => EventRole::EventOwner->value,
            ]);
        });
    }

    public static function uniqueSlug(string $title, ?int $organizationId): string
    {
        $base = Str::slug($title) ?: 'event';
        $slug = $base;
        $i = 1;

        while (static::withTrashed()
            ->where('organization_id', $organizationId)
            ->where('slug', $slug)
            ->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function goals(): HasMany
    {
        return $this->hasMany(EventGoal::class)->orderBy('priority');
    }

    public function stakeholders(): HasMany
    {
        return $this->hasMany(EventStakeholder::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_user')
            ->withPivot(['role', 'added_by', 'joined_at'])
            ->withTimestamps();
    }

    public function committees(): HasMany
    {
        return $this->hasMany(Committee::class)->orderBy('position');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(EventInvitation::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class)->latest();
    }

    public function roleOf(User $user): ?EventRole
    {
        $pivot = $this->members()->where('user_id', $user->id)->first()?->pivot;

        if (! $pivot) {
            return null;
        }

        $role = EventRole::tryFrom($pivot->role);

        // Head of one or more committees is promoted to committee_head unless higher.
        if ($role === EventRole::Member && $this->headsCommittee($user)) {
            return EventRole::CommitteeHead;
        }

        return $role;
    }

    public function headsCommittee(User $user): bool
    {
        return $this->committees()
            ->where('head_user_id', $user->id)
            ->exists();
    }

    public function hasMember(User $user): bool
    {
        return $this->members()->where('user_id', $user->id)->exists();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            EventStatus::Draft->value,
            EventStatus::Planning->value,
            EventStatus::Ready->value,
            EventStatus::Running->value,
        ]);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            $q->where('created_by', $user->id)
                ->orWhereHas('members', fn (Builder $m) => $m->where('user_id', $user->id));

            $orgIds = $user->organizations()
                ->wherePivotIn('role', ['owner', 'admin', 'manager'])
                ->pluck('organizations.id');

            if ($orgIds->isNotEmpty()) {
                $q->orWhereIn('organization_id', $orgIds);
            }
        });
    }

    public function isActive(): bool
    {
        return $this->status?->isActive() ?? false;
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
