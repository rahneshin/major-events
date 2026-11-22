<?php

declare(strict_types=1);

namespace App\Livewire\Events;

use App\Enums\EventRole;
use App\Exceptions\LimitExceededException;
use App\Models\Committee;
use App\Models\Event;
use App\Models\User;
use App\Services\CommitteeService;
use App\Services\EventMemberService;
use App\Services\HumanResourceService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * تب «منابع انسانی» رویداد (جایگزین تب «اعضا»):
 * ثبت نیرو با موبایل، دادن کمیته و پست، تغییر پست، رئیس‌کردن و حذف.
 */
class EventHumanResources extends Component
{
    public Event $event;

    // فرم ثبت
    public string $phone = '';

    public string $name = '';

    public string $eventRole = 'member';

    public string $committeeId = '';

    public string $post = '';

    public bool $asHead = false;

    /** @var array<int, string> افزودن به کمیته‌ی دیگر، به‌ازای userId */
    public array $rowCommittee = [];

    /** @var array<int, string> */
    public array $rowPost = [];

    /** @var array<string, string> ویرایش پست، کلید "committeeId-userId" */
    public array $postDraft = [];

    public ?string $error = null;

    public ?string $notice = null;

    public function mount(Event $event): void
    {
        $this->authorize('view', $event);
        $this->event = $event;
        $this->loadDrafts();
    }

    public function enroll(HumanResourceService $hr): void
    {
        $this->authorize('manageMembers', $this->event);
        $this->reset(['error', 'notice']);

        $this->validate([
            'phone' => ['required', 'string', 'min:10', 'max:15'],
            'name' => ['nullable', 'string', 'max:120'],
            'eventRole' => ['required', 'in:event_manager,member,viewer'],
            'committeeId' => ['nullable', 'integer'],
            'post' => ['nullable', 'string', 'max:120'],
            'asHead' => ['boolean'],
        ]);

        try {
            $committee = $this->committeeId !== ''
                ? $this->event->committees()->findOrFail((int) $this->committeeId)
                : null;

            $result = $hr->enroll(
                $this->event,
                Auth::user(),
                $this->phone,
                $this->name,
                EventRole::from($this->eventRole),
                $committee,
                $this->post,
                $this->asHead && $committee !== null,
            );
        } catch (ModelNotFoundException) {
            $this->error = __('hr.committee_not_in_event');

            return;
        } catch (LimitExceededException|\InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->notice = match ($result['status']) {
            'invited' => $result['sms_sent'] ? __('hr.invited_notice') : __('hr.invited_notice_sms_failed'),
            'assigned' => __('hr.assigned_notice'),
            default => __('hr.unchanged_notice'),
        };

        $this->reset(['phone', 'name', 'committeeId', 'post', 'asHead']);
        $this->afterChange();
    }

    public function assign(int $userId, HumanResourceService $hr): void
    {
        $this->authorize('manageMembers', $this->event);
        $this->reset(['error', 'notice']);

        $committeeId = (int) ($this->rowCommittee[$userId] ?? 0);
        if ($committeeId === 0) {
            $this->error = __('hr.pick_committee');

            return;
        }

        try {
            $hr->assignToCommittee(
                $this->committeeOf($committeeId),
                Auth::user(),
                $this->memberOf($userId),
                $this->rowPost[$userId] ?? null,
            );
        } catch (ModelNotFoundException) {
            $this->error = __('hr.committee_not_in_event');

            return;
        } catch (LimitExceededException|\InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }

        unset($this->rowCommittee[$userId], $this->rowPost[$userId]);
        $this->notice = __('hr.assigned');
        $this->afterChange();
    }

    public function savePost(int $committeeId, int $userId, CommitteeService $committees): void
    {
        $this->authorize('manageMembers', $this->event);
        $this->reset(['error', 'notice']);

        try {
            $committees->setPost(
                $this->committeeOf($committeeId),
                $this->memberOf($userId),
                $this->postDraft["$committeeId-$userId"] ?? null,
            );
        } catch (ModelNotFoundException) {
            $this->error = __('hr.committee_not_in_event');

            return;
        } catch (\InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->notice = __('hr.post_saved');
        $this->afterChange();
    }

    public function toggleHead(int $committeeId, int $userId, CommitteeService $committees): void
    {
        $this->authorize('manageMembers', $this->event);
        $this->reset(['error', 'notice']);

        try {
            $committee = $this->committeeOf($committeeId);
            $user = $this->memberOf($userId);

            $committee->isHead($user)
                ? $committees->clearHead($committee)
                : $committees->setHead($committee, $user);
        } catch (ModelNotFoundException) {
            $this->error = __('hr.committee_not_in_event');

            return;
        } catch (\InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->afterChange();
    }

    public function removeFromCommittee(int $committeeId, int $userId, CommitteeService $committees): void
    {
        $this->authorize('manageMembers', $this->event);
        $this->reset(['error', 'notice']);

        try {
            $committees->removeMember($this->committeeOf($committeeId), $this->memberOf($userId));
        } catch (ModelNotFoundException) {
            $this->error = __('hr.committee_not_in_event');

            return;
        } catch (\InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->afterChange();
    }

    public function resendInvitation(int $invitationId, HumanResourceService $hr): void
    {
        $this->authorize('manageMembers', $this->event);
        $this->reset(['error', 'notice']);

        try {
            $sent = $hr->resend($this->event->invitations()->findOrFail($invitationId), Auth::user());
        } catch (ModelNotFoundException) {
            $this->error = __('hr.invite_invalid');

            return;
        } catch (\InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->notice = $sent ? __('hr.resent') : __('hr.invited_notice_sms_failed');
    }

    public function revokeInvitation(int $invitationId, HumanResourceService $hr): void
    {
        $this->authorize('manageMembers', $this->event);
        $this->reset(['error', 'notice']);

        try {
            $hr->revoke($this->event->invitations()->findOrFail($invitationId));
        } catch (ModelNotFoundException) {
            $this->error = __('hr.invite_invalid');

            return;
        } catch (\InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->notice = __('hr.revoked');
    }

    public function changeRole(int $userId, string $role, EventMemberService $members): void
    {
        $this->authorize('manageMembers', $this->event);
        $this->reset(['error', 'notice']);

        try {
            $members->changeRole($this->event, Auth::user(), $this->memberOf($userId), EventRole::from($role));
        } catch (\Throwable $e) {
            $this->error = $e instanceof \InvalidArgumentException ? $e->getMessage() : __('hr.role_not_assignable');

            return;
        }

        $this->notice = __('event.role_updated');
        $this->afterChange();
    }

    public function removeMember(int $userId, EventMemberService $members): void
    {
        $this->authorize('manageMembers', $this->event);
        $this->reset(['error', 'notice']);

        try {
            $members->removeMember($this->event, Auth::user(), $this->memberOf($userId));
        } catch (ModelNotFoundException) {
            $this->error = __('event.member_not_found');

            return;
        } catch (\InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->notice = __('event.member_removed');
        $this->afterChange();
    }

    public function render()
    {
        $committees = $this->event->committees()->with('members')->get();

        // userId ← [{committee, role, post}]
        $assignments = [];
        foreach ($committees as $committee) {
            foreach ($committee->members as $member) {
                $assignments[$member->id][] = [
                    'committee' => $committee,
                    'is_head' => $committee->isHead($member),
                    'post' => $member->pivot->post,
                ];
            }
        }

        $members = $this->event->members()->get()
            ->sortBy([
                fn ($a, $b) => (EventRole::tryFrom($b->pivot->role)?->rank() ?? 0) <=> (EventRole::tryFrom($a->pivot->role)?->rank() ?? 0),
                fn ($a, $b) => strcmp((string) $a->name, (string) $b->name),
            ])
            ->values();

        return view('livewire.events.event-human-resources', [
            'members' => $members,
            'committees' => $committees,
            'assignments' => $assignments,
            'pendingInvitations' => $this->event->invitations()
                ->with('committee')
                ->where('status', 'pending')
                ->where('expires_at', '>', now())
                ->get(),
            'canManage' => Auth::user()->can('manageMembers', $this->event),
            'assignableRoles' => \App\Services\HumanResourceService::ASSIGNABLE_ROLES,
        ]);
    }

    private function afterChange(): void
    {
        $this->event->unsetRelation('members');
        $this->event->unsetRelation('committees');
        $this->loadDrafts();
    }

    private function loadDrafts(): void
    {
        $this->postDraft = [];

        foreach ($this->event->committees()->with('members')->get() as $committee) {
            foreach ($committee->members as $member) {
                $this->postDraft["{$committee->id}-{$member->id}"] = (string) ($member->pivot->post ?? '');
            }
        }
    }

    private function committeeOf(int $id): Committee
    {
        return $this->event->committees()->findOrFail($id);
    }

    private function memberOf(int $userId): User
    {
        return $this->event->members()->where('users.id', $userId)->firstOrFail();
    }
}
