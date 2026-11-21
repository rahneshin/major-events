<?php

namespace App\Models;

use App\Enums\AccountType;
use App\Enums\OrgRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'phone',
        'phone_verified_at',
        'account_type',
        'current_organization_id',
        'avatar_path',
        'last_login_at',
        'status',
    ];

    protected $hidden = [
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'account_type' => AccountType::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (empty($user->uuid)) {
                $user->uuid = (string) Str::uuid();
            }
        });
    }

    public function accountUpgrades(): HasMany
    {
        return $this->hasMany(AccountUpgrade::class);
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class)
            ->withPivot(['role', 'joined_at'])
            ->withTimestamps();
    }

    public function ownedOrganizations(): HasMany
    {
        return $this->hasMany(Organization::class, 'owner_id');
    }

    public function currentOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'current_organization_id');
    }

    public function committees(): BelongsToMany
    {
        return $this->belongsToMany(Committee::class, 'committee_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_user')
            ->withPivot(['role', 'added_by', 'joined_at'])
            ->withTimestamps();
    }

    public function isProfileComplete(): bool
    {
        return filled($this->name);
    }

    public function hasSelectedAccountType(): bool
    {
        return $this->account_type !== null;
    }

    public function isOnboarded(): bool
    {
        return $this->isProfileComplete() && $this->hasSelectedAccountType();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isIndividual(): bool
    {
        return $this->account_type === AccountType::Individual;
    }

    public function isOrganizational(): bool
    {
        return $this->account_type === AccountType::Organizational;
    }

    public function orgRoleIn(Organization $organization): ?OrgRole
    {
        return $organization->roleOf($this);
    }
}
