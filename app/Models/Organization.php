<?php

namespace App\Models;

use App\Enums\OrganizationType;
use App\Enums\OrgRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Organization extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'type',
        'description',
        'city',
        'logo_path',
        'owner_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'type' => OrganizationType::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Organization $org) {
            if (empty($org->uuid)) {
                $org->uuid = (string) Str::uuid();
            }

            if (empty($org->slug)) {
                $org->slug = static::uniqueSlug($org->name);
            }
        });
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'org';
        }

        $slug = $base;
        $i = 1;

        while (static::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role', 'joined_at'])
            ->withTimestamps();
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(OrganizationInvitation::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function roleOf(User $user): ?OrgRole
    {
        $pivot = $this->members()->where('user_id', $user->id)->first()?->pivot;

        if ($pivot === null) {
            return null;
        }

        return OrgRole::tryFrom($pivot->role);
    }

    public function hasMember(User $user): bool
    {
        return $this->members()->where('user_id', $user->id)->exists();
    }
}
