<?php

namespace App\Models;

use App\Enums\AccountType;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
}
