<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MilestoneStatus;
use App\Models\Event;
use App\Models\Milestone;
use App\Support\LogsActivity;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Throwable;

/**
 * Step 08a — event milestones (key dates such as "registration closes").
 * Authorization happens in the caller via MilestonePolicy.
 */
class MilestoneService
{
    use LogsActivity;

    public const MAX_TITLE_LENGTH = 200;

    /**
     * @param  array{title: string, description?: ?string, due_at?: mixed}  $data
     */
    public function create(Event $event, array $data): Milestone
    {
        $milestone = $event->milestones()->create([
            'title' => $this->validTitle($data['title'] ?? ''),
            'description' => $this->nullableText($data['description'] ?? null),
            'due_at' => $this->parseDate($data['due_at'] ?? null),
            'status' => MilestoneStatus::Pending,
        ]);

        self::logActivity('milestone.created', $milestone, $event->id, ['title' => $milestone->title]);

        return $milestone;
    }

    /**
     * @param  array{title?: string, description?: ?string, due_at?: mixed}  $data
     */
    public function update(Milestone $milestone, array $data): Milestone
    {
        $payload = [];

        if (array_key_exists('title', $data)) {
            $payload['title'] = $this->validTitle($data['title'] ?? '');
        }
        if (array_key_exists('description', $data)) {
            $payload['description'] = $this->nullableText($data['description']);
        }
        if (array_key_exists('due_at', $data)) {
            $payload['due_at'] = $this->parseDate($data['due_at']);
        }

        if ($payload !== []) {
            $milestone->update($payload);

            self::logActivity('milestone.updated', $milestone, $milestone->event_id, [
                'changes' => array_values(array_diff(array_keys($milestone->getChanges()), ['updated_at'])),
            ]);
        }

        return $milestone->refresh();
    }

    public function setStatus(Milestone $milestone, MilestoneStatus $status): Milestone
    {
        if ($milestone->status === $status) {
            return $milestone;
        }

        $from = $milestone->status;

        $milestone->update([
            'status' => $status,
            'reached_at' => $status === MilestoneStatus::Reached ? now() : null,
        ]);

        self::logActivity('milestone.status_changed', $milestone, $milestone->event_id, [
            'from' => $from?->value,
            'to' => $status->value,
        ]);

        return $milestone->refresh();
    }

    public function delete(Milestone $milestone): void
    {
        self::logActivity('milestone.deleted', $milestone, $milestone->event_id, ['title' => $milestone->title]);

        $milestone->delete();
    }

    private function validTitle(mixed $title): string
    {
        $title = trim((string) $title);

        if ($title === '') {
            throw new InvalidArgumentException(__('task.milestone_title_required'));
        }
        if (mb_strlen($title) > self::MAX_TITLE_LENGTH) {
            throw new InvalidArgumentException(__('task.title_too_long', ['max' => self::MAX_TITLE_LENGTH]));
        }

        return $title;
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value);
        }

        try {
            return Carbon::parse((string) $value);
        } catch (Throwable) {
            throw new InvalidArgumentException(__('task.date_invalid'));
        }
    }
}
