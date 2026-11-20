<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\EventRole;
use App\Enums\OrgRole;
use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOnboarded();
    }

    public function view(User $user, Event $event): bool
    {
        return $this->isMember($user, $event)
            || $this->isCreator($user, $event)
            || $this->isOrgManager($user, $event);
    }

    public function create(User $user): bool
    {
        return $user->isOnboarded();
    }

    public function update(User $user, Event $event): bool
    {
        $role = $event->roleOf($user);

        return ($role?->canUpdateEvent() ?? false)
            || $this->isCreator($user, $event)
            || $this->isOrgManager($user, $event);
    }

    public function delete(User $user, Event $event): bool
    {
        $role = $event->roleOf($user);

        return $role === EventRole::EventOwner
            || $this->isCreator($user, $event)
            || $this->isOrgOwner($user, $event);
    }

    public function transition(User $user, Event $event): bool
    {
        return $this->update($user, $event);
    }

    public function manageMembers(User $user, Event $event): bool
    {
        $role = $event->roleOf($user);

        return ($role?->canManageMembers() ?? false)
            || $this->isOrgManager($user, $event);
    }

    private function isCreator(User $user, Event $event): bool
    {
        return (int) $event->created_by === (int) $user->id;
    }

    private function isMember(User $user, Event $event): bool
    {
        return $event->hasMember($user);
    }

    private function isOrgManager(User $user, Event $event): bool
    {
        if (! $event->organization_id || ! $event->organization) {
            return false;
        }

        $role = $user->orgRoleIn($event->organization);

        return $role !== null && in_array($role, [
            OrgRole::Owner,
            OrgRole::Admin,
            OrgRole::Manager,
        ], true);
    }

    private function isOrgOwner(User $user, Event $event): bool
    {
        if (! $event->organization_id || ! $event->organization) {
            return false;
        }

        return $user->orgRoleIn($event->organization) === OrgRole::Owner;
    }
}
