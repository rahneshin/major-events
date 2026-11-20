<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EventStatus;
use App\Enums\GoalKind;
use App\Exceptions\LimitExceededException;
use App\Models\Event;
use App\Models\EventGoal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class EventService
{
    public function __construct(
        private EntitlementService $entitlement,
    ) {}

    public function activeCountFor(User $user): int
    {
        return Event::query()
            ->where('created_by', $user->id)
            ->active()
            ->count();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $goals
     */
    public function create(User $user, array $data, array $goals = []): Event
    {
        $this->entitlement->assertWithinLimit(
            $user,
            'active_events',
            $this->activeCountFor($user),
        );

        return DB::transaction(function () use ($user, $data, $goals) {
            $orgId = $data['organization_id'] ?? $user->current_organization_id;

            if ($orgId && $user->isIndividual()) {
                $orgId = null;
            }

            $event = Event::query()->create([
                'organization_id' => $orgId,
                'created_by' => $user->id,
                'title' => $data['title'],
                'slug' => Event::uniqueSlug($data['title'], $orgId),
                'sport_type' => $data['sport_type'] ?? null,
                'event_type' => $data['event_type'] ?? 'other',
                'scale' => $data['scale'] ?? 'medium',
                'status' => EventStatus::Draft,
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
                'venue_name' => $data['venue_name'] ?? null,
                'city' => $data['city'] ?? null,
                'province' => $data['province'] ?? null,
                'expected_participants' => $data['expected_participants'] ?? null,
                'expected_spectators' => $data['expected_spectators'] ?? null,
                'budget_estimate' => $data['budget_estimate'] ?? null,
                'main_goal_type' => $data['main_goal_type'] ?? null,
                'description' => $data['description'] ?? null,
            ]);

            $this->syncGoals($event, $goals);

            return $event->fresh(['goals']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $goals
     */
    public function update(Event $event, array $data, array $goals = []): Event
    {
        return DB::transaction(function () use ($event, $data, $goals) {
            $event->fill([
                'title' => $data['title'] ?? $event->title,
                'sport_type' => $data['sport_type'] ?? $event->sport_type,
                'event_type' => $data['event_type'] ?? $event->event_type,
                'scale' => $data['scale'] ?? $event->scale,
                'start_date' => array_key_exists('start_date', $data) ? $data['start_date'] : $event->start_date,
                'end_date' => array_key_exists('end_date', $data) ? $data['end_date'] : $event->end_date,
                'venue_name' => $data['venue_name'] ?? $event->venue_name,
                'city' => $data['city'] ?? $event->city,
                'province' => $data['province'] ?? $event->province,
                'expected_participants' => $data['expected_participants'] ?? $event->expected_participants,
                'expected_spectators' => $data['expected_spectators'] ?? $event->expected_spectators,
                'budget_estimate' => $data['budget_estimate'] ?? $event->budget_estimate,
                'main_goal_type' => $data['main_goal_type'] ?? $event->main_goal_type,
                'description' => $data['description'] ?? $event->description,
            ]);

            if (isset($data['title']) && $data['title'] !== $event->getOriginal('title')) {
                $event->slug = Event::uniqueSlug($data['title'], $event->organization_id);
            }

            $event->save();
            $this->syncGoals($event, $goals);

            return $event->fresh(['goals']);
        });
    }

    public function transition(Event $event, EventStatus $to): Event
    {
        if (! $event->status->canTransitionTo($to)) {
            throw new InvalidArgumentException(
                __('event.invalid_transition', [
                    'from' => $event->status->label(),
                    'to' => $to->label(),
                ])
            );
        }

        $event->status = $to;
        $event->save();

        return $event;
    }

    public function archive(Event $event): Event
    {
        if ($event->status !== EventStatus::Completed) {
            $event->status = EventStatus::Archived;
        } else {
            $this->transition($event, EventStatus::Archived);
        }

        $event->save();

        return $event;
    }

    /**
     * @param  list<array<string, mixed>>  $goals
     */
    private function syncGoals(Event $event, array $goals): void
    {
        $event->goals()->delete();

        foreach ($goals as $i => $goal) {
            if (blank($goal['title'] ?? null)) {
                continue;
            }

            EventGoal::query()->create([
                'event_id' => $event->id,
                'kind' => $goal['kind'] ?? GoalKind::Secondary->value,
                'title' => $goal['title'],
                'description' => $goal['description'] ?? null,
                'priority' => $goal['priority'] ?? ($i + 1),
                'smart_measure' => $goal['smart_measure'] ?? null,
                'target_value' => $goal['target_value'] ?? null,
                'unit' => $goal['unit'] ?? null,
            ]);
        }
    }
}
