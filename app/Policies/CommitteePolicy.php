<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Committee;
use App\Models\Event;
use App\Models\User;

/**
 * Committee access:
 *  - view: anyone who can view the event
 *  - create / update / delete / reorder: event owner/manager or org manager (= EventPolicy@update)
 *  - manageMembers / updateDescription: the above, plus the head of that committee
 */
class CommitteePolicy
{
    public function viewAny(User $user, Event $event): bool
    {
        return $user->can('view', $event);
    }

    public function view(User $user, Committee $committee): bool
    {
        return $user->can('view', $committee->event);
    }

    public function create(User $user, Event $event): bool
    {
        return $user->can('update', $event);
    }

    public function update(User $user, Committee $committee): bool
    {
        return $user->can('update', $committee->event);
    }

    public function delete(User $user, Committee $committee): bool
    {
        return $user->can('update', $committee->event);
    }

    public function reorder(User $user, Event $event): bool
    {
        return $user->can('update', $event);
    }

    public function updateDescription(User $user, Committee $committee): bool
    {
        return $this->update($user, $committee) || $committee->isHead($user);
    }

    public function manageMembers(User $user, Committee $committee): bool
    {
        return $this->update($user, $committee) || $committee->isHead($user);
    }
}
