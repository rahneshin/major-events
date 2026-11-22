<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Event;
use App\Models\Milestone;
use App\Models\User;

/**
 * Milestones: anyone who can view the event can see them;
 * only manager-level users (EventPolicy@update) change them.
 */
class MilestonePolicy
{
    public function viewAny(User $user, Event $event): bool
    {
        return $user->can('view', $event);
    }

    public function view(User $user, Milestone $milestone): bool
    {
        return $user->can('view', $milestone->event);
    }

    public function create(User $user, Event $event): bool
    {
        return $user->can('update', $event);
    }

    public function update(User $user, Milestone $milestone): bool
    {
        return $user->can('update', $milestone->event);
    }

    public function delete(User $user, Milestone $milestone): bool
    {
        return $user->can('update', $milestone->event);
    }
}
