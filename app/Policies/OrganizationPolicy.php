<?php

namespace App\Policies;

use App\Enums\OrgRole;
use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOrganizational() || $user->organizations()->exists();
    }

    public function view(User $user, Organization $organization): bool
    {
        return $organization->hasMember($user);
    }

    public function create(User $user): bool
    {
        return $user->isOrganizational();
    }

    public function update(User $user, Organization $organization): bool
    {
        $role = $organization->roleOf($user);

        return $role?->canUpdateOrganization() ?? false;
    }

    public function delete(User $user, Organization $organization): bool
    {
        return $user->id === $organization->owner_id;
    }

    public function manageMembers(User $user, Organization $organization): bool
    {
        $role = $organization->roleOf($user);

        return $role?->canManageMembers() ?? false;
    }

    public function invite(User $user, Organization $organization): bool
    {
        return $this->manageMembers($user, $organization);
    }

    public function transferOwnership(User $user, Organization $organization): bool
    {
        return $user->id === $organization->owner_id;
    }
}
