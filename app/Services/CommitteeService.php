<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CommitteeRole;
use App\Enums\CommitteeType;
use App\Enums\EventRole;
use App\Models\Committee;
use App\Models\Event;
use App\Models\User;
use App\Notifications\AddedToCommittee;
use App\Support\LogsActivity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CommitteeService
{
    use LogsActivity;

    public function __construct(
        private EntitlementService $entitlement,
        private EventMemberService $eventMembers,
    ) {}

    public function count(Event $event): int
    {
        // reorder(): aggregates with ORDER BY fail on PostgreSQL
        return $event->committees()->reorder()->count();
    }

    /**
     * @param  array{name: string, type?: string|CommitteeType|null, description?: ?string, color?: ?string}  $data
     *
     * @throws \App\Exceptions\LimitExceededException
     */
    public function create(Event $event, User $actor, array $data): Committee
    {
        $name = $this->validName($event, $data['name'] ?? '');

        $this->entitlement->assertWithinLimit($actor, 'committees_per_event', $this->count($event));

        return DB::transaction(function () use ($event, $actor, $data, $name) {
            $type = $this->resolveType($data['type'] ?? null);

            $committee = $event->committees()->create([
                'name' => $name,
                'type' => $type,
                'description' => $data['description'] ?? ($type->description() ?: null),
                'color' => $data['color'] ?? $type->color(),
                'position' => ((int) $event->committees()->reorder()->max('position')) + 1,
            ]);

            self::logActivity('committee.created', $committee, $event->id, [
                'name' => $committee->name,
                'type' => $type->value,
            ]);

            if (! empty($data['head_user_id'])) {
                $this->assignHead($committee, $actor, (int) $data['head_user_id']);
                // assignHead works on a fresh copy; never hand back a stale model
                $committee->refresh();
            }

            return $committee;
        });
    }

    /**
     * @param  array{name?: string, type?: string|CommitteeType|null, description?: ?string, color?: ?string}  $data
     */
    public function update(Committee $committee, array $data, ?User $actor = null): Committee
    {
        $payload = [];

        if (array_key_exists('name', $data)) {
            $payload['name'] = $this->validName($committee->event, $data['name'] ?? '', $committee);
        }
        if (array_key_exists('type', $data)) {
            $payload['type'] = $this->resolveType($data['type']);
        }
        if (array_key_exists('description', $data)) {
            $payload['description'] = $data['description'];
        }
        if (array_key_exists('color', $data) && $data['color']) {
            $payload['color'] = $data['color'];
        }

        $committee->update($payload);

        self::logActivity('committee.updated', $committee, $committee->event_id, [
            'changes' => array_keys($committee->getChanges()),
        ]);

        if (array_key_exists('head_user_id', $data)) {
            $newHead = $data['head_user_id'] ? (int) $data['head_user_id'] : null;

            if ($newHead !== ($committee->head_user_id ? (int) $committee->head_user_id : null)) {
                $newHead
                    ? $this->assignHead($committee, $actor ?? $committee->event->creator, $newHead)
                    : $this->clearHead($committee);
            }
        }

        return $committee->fresh();
    }

    /**
     * Update only the description (allowed for the committee head).
     */
    public function updateDescription(Committee $committee, ?string $description): Committee
    {
        return $this->update($committee, ['description' => $description]);
    }

    public function delete(Committee $committee): void
    {
        if ($committee->hasTasks()) {
            throw new InvalidArgumentException(__('committee.has_tasks'));
        }

        DB::transaction(function () use ($committee) {
            $event = $committee->event;

            self::logActivity('committee.deleted', $committee, $event->id, ['name' => $committee->name]);

            $committee->delete();
            $this->normalizePositions($event);
        });
    }

    /**
     * @param  list<int>  $orderedIds
     */
    public function reorder(Event $event, array $orderedIds): void
    {
        DB::transaction(function () use ($event, $orderedIds) {
            $valid = $event->committees()->pluck('id')->all();
            $position = 1;

            foreach ($orderedIds as $id) {
                if (in_array((int) $id, $valid, true)) {
                    Committee::query()->whereKey($id)->update(['position' => $position++]);
                }
            }

            $this->normalizePositions($event);
        });
    }

    public function move(Committee $committee, string $direction): void
    {
        $ids = $committee->event->committees()->pluck('id')->all();
        $index = array_search($committee->id, $ids, true);

        if ($index === false) {
            return;
        }

        $target = $direction === 'up' ? $index - 1 : $index + 1;
        if (! isset($ids[$target])) {
            return;
        }

        [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];
        $this->reorder($committee->event, $ids);
    }

    /**
     * Create suggested committees for the event type; skips those already present
     * and stops at the plan's committee limit.
     *
     * @return Collection<int, Committee>
     *
     * @throws \App\Exceptions\LimitExceededException when no slot is left at all
     */
    public function applyTemplate(Event $event, User $actor): Collection
    {
        $existing = $event->committees()->pluck('type')->map(fn ($t) => $t instanceof CommitteeType ? $t->value : $t)->all();
        $missing = collect(CommitteeType::templateFor($event->event_type))
            ->reject(fn (CommitteeType $t) => in_array($t->value, $existing, true))
            ->values();

        $created = collect();

        foreach ($missing as $type) {
            if (! $this->entitlement->isWithinLimit($actor, 'committees_per_event', $this->count($event))) {
                if ($created->isEmpty()) {
                    $this->entitlement->assertWithinLimit($actor, 'committees_per_event', $this->count($event));
                }
                break;
            }

            $created->push($this->create($event, $actor, [
                'name' => $type->label(),
                'type' => $type,
            ]));
        }

        return $created;
    }

    /**
     * Add an event member to a committee.
     * If the user is not yet an event member, they are added to the event as well
     * only when $addToEvent is true (limit members_per_event applies).
     */
    public function addMember(
        Committee $committee,
        User $actor,
        User $user,
        CommitteeRole $role = CommitteeRole::Member,
        bool $addToEvent = false,
        ?string $post = null,
    ): void {
        $event = $committee->event;

        if (! $event->hasMember($user)) {
            if (! $addToEvent) {
                throw new InvalidArgumentException(__('committee.must_be_event_member'));
            }
            $this->eventMembers->addMember($event, $actor, $user, EventRole::Member);
        }

        if ($committee->hasMember($user)) {
            throw new InvalidArgumentException(__('committee.member_already'));
        }

        $post = $this->cleanPost($post);

        DB::transaction(function () use ($committee, $user, $role, $event, $post) {
            $committee->members()->attach($user->id, [
                'role' => CommitteeRole::Member->value,
                'post' => $post,
            ]);

            self::logActivity('committee.member_added', $committee, $event->id, [
                'member_id' => $user->id,
            ]);

            if ($role === CommitteeRole::Head) {
                $this->setHead($committee, $user);
            }
        });

        if ($user->id !== $actor->id) {
            $user->notify(new AddedToCommittee($committee->fresh('event'), $role->value));
        }
    }

    /**
     * «پست» = عنوان سازمانی آزاد فرد داخل کمیته (مثلاً «مسئول پذیرایی»).
     * مستقل از نقش رئیس/عضو است و هر زمان قابل تغییر.
     */
    public function setPost(Committee $committee, User $user, ?string $post): void
    {
        if (! $committee->hasMember($user)) {
            throw new InvalidArgumentException(__('committee.member_not_found'));
        }

        $post = $this->cleanPost($post);
        $committee->members()->updateExistingPivot($user->id, ['post' => $post]);

        self::logActivity('committee.post_changed', $committee, $committee->event_id, [
            'member_id' => $user->id,
            'post' => $post,
        ]);
    }

    private function cleanPost(?string $post): ?string
    {
        $post = trim((string) $post);

        return $post === '' ? null : mb_substr($post, 0, 120);
    }

    public function removeMember(Committee $committee, User $user): void
    {
        if (! $committee->hasMember($user)) {
            throw new InvalidArgumentException(__('committee.member_not_found'));
        }

        DB::transaction(function () use ($committee, $user) {
            $wasHead = $committee->isHead($user);

            $committee->members()->detach($user->id);

            if ($wasHead) {
                $committee->update(['head_user_id' => null]);
            }

            self::logActivity('committee.member_removed', $committee, $committee->event_id, [
                'member_id' => $user->id,
                'was_head' => $wasHead,
            ]);
        });
    }

    /**
     * Make a committee member the head. The event-level role is derived
     * (Event::roleOf promotes a plain member heading a committee to committee_head),
     * so nothing needs to be synced or demoted here.
     *
     * Make a committee member the head (previous head becomes a regular member).
     */
    public function setHead(Committee $committee, User $user): void
    {
        if (! $committee->hasMember($user)) {
            throw new InvalidArgumentException(__('committee.head_must_be_member'));
        }

        DB::transaction(function () use ($committee, $user) {
            $previousId = $committee->head_user_id;

            if ($previousId && (int) $previousId !== (int) $user->id) {
                $committee->members()->updateExistingPivot($previousId, ['role' => CommitteeRole::Member->value]);
            }

            $committee->members()->updateExistingPivot($user->id, ['role' => CommitteeRole::Head->value]);
            $committee->update(['head_user_id' => $user->id]);

            self::logActivity('committee.head_changed', $committee, $committee->event_id, [
                'from' => $previousId,
                'to' => $user->id,
            ]);
        });
    }

    /**
     * Head chosen in the committee form: must be (or become) an event member,
     * then a committee member, then head.
     */
    private function assignHead(Committee $committee, User $actor, int $userId): void
    {
        $user = User::query()->find($userId);
        if (! $user) {
            throw new InvalidArgumentException(__('committee.head_not_found'));
        }

        if (! $committee->hasMember($user)) {
            $this->addMember($committee, $actor, $user, CommitteeRole::Member, addToEvent: true);
        }

        $this->setHead($committee->fresh(), $user);
    }

    public function clearHead(Committee $committee): void
    {
        $previous = $committee->head_user_id;
        if (! $previous) {
            return;
        }

        DB::transaction(function () use ($committee, $previous) {
            $committee->members()->updateExistingPivot($previous, ['role' => CommitteeRole::Member->value]);
            $committee->update(['head_user_id' => null]);

            self::logActivity('committee.head_changed', $committee, $committee->event_id, [
                'from' => $previous,
                'to' => null,
            ]);
        });
    }

    /**
     * Trimmed, non-empty and unique among the event's (non-deleted) committees.
     */
    private function validName(Event $event, mixed $name, ?Committee $except = null): string
    {
        $name = trim((string) $name);

        if ($name === '') {
            throw new InvalidArgumentException(__('committee.name_required'));
        }

        $duplicate = $event->committees()
            ->where('name', $name)
            ->when($except, fn ($q) => $q->whereKeyNot($except->getKey()))
            ->exists();

        if ($duplicate) {
            throw new InvalidArgumentException(__('committee.name_duplicate'));
        }

        return $name;
    }

    private function resolveType(string|CommitteeType|null $type): CommitteeType
    {
        if ($type instanceof CommitteeType) {
            return $type;
        }

        return CommitteeType::tryFrom((string) $type) ?? CommitteeType::Custom;
    }

    private function normalizePositions(Event $event): void
    {
        $position = 1;
        foreach ($event->committees()->pluck('id') as $id) {
            Committee::query()->whereKey($id)->update(['position' => $position++]);
        }
    }
}
