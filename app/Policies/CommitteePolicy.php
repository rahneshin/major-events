<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Committee;
use App\Models\User;

class CommitteePolicy
{
    public function view(User $user, Committee $committee): bool
    {
        return $user->can('view', $committee->event);
    }

    public function create(User $user, Committee $committee): bool
    {
        return $user->can('manageMembers', $committee->event);
    }

    public function update(User $user, Committee $committee): bool
    {
        return $user->can('manageMembers', $committee->event)
            || $committee->isHead($user);
    }

    public function delete(User $user, Committee $committee): bool
    {
        return $user->can('manageMembers', $committee->event);
    }

    public function manageMembers(User $user, Committee $committee): bool
    {
        return $user->can('manageMembers', $committee->event)
            || $committee->isHead($user);
    }
}
