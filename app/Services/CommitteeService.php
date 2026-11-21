<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CommitteeRole;
use App\Enums\CommitteeType;
use App\Enums\EventRole;
use App\Models\Committee;
use App\Models\Event;
use App\Models\User;
use App\Support\LogsActivity;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CommitteeService
{
    use LogsActivity;

    public function __construct(
        private EntitlementService $entitlement,
        private EventMemberService $members,
    ) {}

    public function create(Event $event, User $actor, array $data): Committee
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException(__('committee.name_required'));
        }

        if ($event->committees()->where('name', $name)->exists()) {
            throw new InvalidArgumentException(__('committee.name_duplicate'));
        }

        $this->entitlement->assertWithinLimit(
            $actor,
            'committees_per_event',
            $event->committees()->count(),
        );

        $type = $data['type'] ?? CommitteeType::Custom;
        $type = $type instanceof CommitteeType ? $type : CommitteeType::tryFrom((string) $type) ?? CommitteeType::Custom;

        $headId = isset($data['head_user_id']) ? (int) $data['head_user_id'] : null;

        return DB::transaction(function () use ($event, $actor, $data, $name, $type, $headId) {
            $committee = $event->committees()->create([
                'name' => $name,
                'type' => $type,
                'description' => $data['description'] ?? null,
                'head_user_id' => $headId,
                'color' => $data['color'] ?? $type->color(),
                'position' => $event->committees()->count(),
            ]);

            if ($headId) {
                $this->setHead($event, $committee, $actor, $headId);
            }

            self::logActivity('committee.created', $committee, $event->id, [
                'name' => $committee->name,
                'type' => $type->value,
            ]);

            return $committee;
        });
    }

    public function update(Event $event, User $actor, Committee $committee, array $data): Committee
    {
        $name = trim((string) ($data['name'] ?? $committee->name));
        if ($name === '') {
            throw new InvalidArgumentException(__('committee.name_required'));
        }

        $duplicate = $event->committees()
            ->where('name', $name)
            ->whereKeyNot($committee->getKey())
            ->exists();
        if ($duplicate) {
            throw new InvalidArgumentException(__('committee.name_duplicate'));
        }

        $previousHead = $committee->head_user_id;

        $committee->fill([
            'name' => $name,
            'description' => $data['description'] ?? $committee->description,
            'color' => $data['color'] ?? $committee->color,
        ]);

        if (array_key_exists('type', $data) && $data['type'] !== null) {
            $committee->type = $data['type'] instanceof CommitteeType
                ? $data['type']
                : CommitteeType::tryFrom((string) $data['type']) ?? $committee->type;
        }

        $committee->save();

        if (array_key_exists('head_user_id', $data)) {
            $newHead = $data['head_user_id'] !== null ? (int) $data['head_user_id'] : null;
            if ($newHead !== (int) $previousHead) {
                if ($newHead) {
                    $this->setHead($event, $committee, $actor, $newHead);
                } else {
                    $this->clearHead($committee);
                }
            }
        }

        self::logActivity('committee.updated', $committee, $event->id, [
            'name' => $committee->name,
        ]);

        return $committee->fresh();
    }

    public function delete(Event $event, User $actor, Committee $committee): void
    {
        $name = $committee->name;
        $committee->delete();

        self::logActivity('committee.deleted', null, $event->id, ['name' => $name]);
    }

    public function addMember(Event $event, User $actor, Committee $committee, User $member, CommitteeRole $role = CommitteeRole::Member): void
    {
        if (! $event->hasMember($member)) {
            $this->members->addMember($event, $actor, $member, EventRole::Member);
        }

        if ($committee->hasMember($member)) {
            $committee->members()->updateExistingPivot($member->id, ['role' => $role->value]);
        } else {
            $committee->members()->attach($member->id, ['role' => $role->value]);
        }

        if ($role === CommitteeRole::Head) {
            $committee->forceFill(['head_user_id' => $member->id])->save();
        }

        self::logActivity('committee.member_added', $member, $event->id, [
            'committee_id' => $committee->id,
            'role' => $role->value,
        ]);
    }

    public function removeMember(Event $event, User $actor, Committee $committee, User $member): void
    {
        $committee->members()->detach($member->id);

        if ((int) $committee->head_user_id === (int) $member->id) {
            $committee->forceFill(['head_user_id' => null])->save();
        }

        self::logActivity('committee.member_removed', $member, $event->id, [
            'committee_id' => $committee->id,
        ]);
    }

    private function setHead(Event $event, Committee $committee, User $actor, int $headId): void
    {
        $head = User::query()->find($headId);
        if (! $head) {
            throw new InvalidArgumentException(__('committee.head_not_found'));
        }

        if (! $event->hasMember($head)) {
            $this->members->addMember($event, $actor, $head, EventRole::Member);
        }

        $committee->forceFill(['head_user_id' => $head->id])->save();
        $committee->members()->syncWithoutDetaching([
            $head->id => ['role' => CommitteeRole::Head->value],
        ]);
    }

    private function clearHead(Committee $committee): void
    {
        $previous = $committee->head_user_id;

        $committee->forceFill(['head_user_id' => null])->save();

        if ($previous) {
            $committee->members()->updateExistingPivot($previous, ['role' => CommitteeRole::Member->value]);
        }
    }
}
