<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\EventRole;
use App\Models\Committee;
use App\Models\Event;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\TaskComment;
use App\Models\User;

/**
 * Task access (matrix: docs/ai/01-DOMAIN-MODEL.md §10, Decision Log Step 08):
 *
 *  - view / viewAny ........ anyone who can view the event
 *  - create ................ event owner/manager or org manager (= EventPolicy@update);
 *                            a committee head only inside THEIR committee
 *  - update / dependencies . manager-level, or the head of the task's committee
 *  - delete ................ manager-level only
 *  - transition (status) ... manager-level, the head of the task's committee,
 *                            or the assignee (never a viewer)
 *  - comment ............... every non-viewer member (+ manager-level)
 *  - attach ................ same people as transition
 *  - deleteComment / removeAttachment: the author/uploader, or manager-level
 *
 * "manager-level" = EventPolicy@update (event_owner, event_manager, creator, org owner/admin/manager).
 */
class TaskPolicy
{
    public function viewAny(User $user, Event $event): bool
    {
        return $user->can('view', $event);
    }

    public function view(User $user, Task $task): bool
    {
        return $user->can('view', $task->event);
    }

    public function create(User $user, Event $event, ?Committee $committee = null): bool
    {
        if ($user->can('update', $event)) {
            return true;
        }

        return $committee !== null
            && (int) $committee->event_id === (int) $event->id
            && $committee->isHead($user);
    }

    public function update(User $user, Task $task): bool
    {
        return $this->isManager($user, $task) || $this->isCommitteeHead($user, $task);
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->isManager($user, $task);
    }

    public function manageDependencies(User $user, Task $task): bool
    {
        return $this->update($user, $task);
    }

    public function transition(User $user, Task $task): bool
    {
        return $this->update($user, $task) || $this->isActiveAssignee($user, $task);
    }

    public function attach(User $user, Task $task): bool
    {
        return $this->transition($user, $task);
    }

    public function comment(User $user, Task $task): bool
    {
        if ($this->isManager($user, $task)) {
            return true;
        }

        return $user->can('view', $task->event)
            && $task->event->roleOf($user) !== EventRole::Viewer;
    }

    public function deleteComment(User $user, Task $task, TaskComment $comment): bool
    {
        if ((int) $comment->task_id !== (int) $task->id) {
            return false;
        }

        return ($comment->user_id !== null && (int) $comment->user_id === (int) $user->id)
            || $this->isManager($user, $task);
    }

    public function removeAttachment(User $user, Task $task, TaskAttachment $attachment): bool
    {
        if ((int) $attachment->task_id !== (int) $task->id) {
            return false;
        }

        return ($attachment->uploaded_by !== null && (int) $attachment->uploaded_by === (int) $user->id)
            || $this->isManager($user, $task);
    }

    private function isManager(User $user, Task $task): bool
    {
        return $user->can('update', $task->event);
    }

    private function isCommitteeHead(User $user, Task $task): bool
    {
        return $task->committee_id !== null
            && $task->committee !== null
            && $task->committee->isHead($user);
    }

    /**
     * The assignee may move their own task, as long as they are still a
     * non-viewer member of the event.
     */
    private function isActiveAssignee(User $user, Task $task): bool
    {
        if ($task->assignee_id === null || (int) $task->assignee_id !== (int) $user->id) {
            return false;
        }

        $role = $task->event->roleOf($user);

        return $role !== null && $role !== EventRole::Viewer;
    }
}
