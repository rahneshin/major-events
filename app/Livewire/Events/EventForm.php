<?php

declare(strict_types=1);

namespace App\Livewire\Events;

use App\Enums\EventScale;
use App\Enums\EventType;
use App\Enums\GoalKind;
use App\Enums\MainGoalType;
use App\Models\Event;
use App\Services\EventService;
use App\Support\Jalali;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class EventForm extends Component
{
    public ?Event $event = null;

    public int $step = 1;

    public string $title = '';

    public string $sport_type = '';

    public string $event_type = 'other';

    public string $scale = 'medium';

    public string $main_goal_type = '';

    public string $description = '';

    public string $start_date_jalali = '';

    public string $end_date_jalali = '';

    public string $venue_name = '';

    public string $city = '';

    public string $province = '';

    public ?int $expected_participants = null;

    public ?int $expected_spectators = null;

    public ?int $budget_estimate = null;

    /** @var list<array{kind: string, title: string, description: string, target_value: string, unit: string}> */
    public array $goals = [];

    public function mount(?Event $event = null): void
    {
        if ($event) {
            $this->authorize('update', $event);
            $this->event = $event;
            $this->fillFromEvent($event);
        } else {
            $this->authorize('create', Event::class);
            $this->goals = [
                ['kind' => GoalKind::Main->value, 'title' => '', 'description' => '', 'target_value' => '', 'unit' => ''],
            ];
        }
    }

    private function fillFromEvent(Event $event): void
    {
        $this->title = $event->title;
        $this->sport_type = $event->sport_type ?? '';
        $this->event_type = $event->event_type?->value ?? 'other';
        $this->scale = $event->scale?->value ?? 'medium';
        $this->main_goal_type = $event->main_goal_type?->value ?? '';
        $this->description = $event->description ?? '';
        $this->start_date_jalali = $event->start_date?->toDateString() ?? '';
        $this->end_date_jalali = $event->end_date?->toDateString() ?? '';
        $this->venue_name = $event->venue_name ?? '';
        $this->city = $event->city ?? '';
        $this->province = $event->province ?? '';
        $this->expected_participants = $event->expected_participants;
        $this->expected_spectators = $event->expected_spectators;
        $this->budget_estimate = $event->budget_estimate;

        $this->goals = $event->goals->map(fn ($g) => [
            'kind' => $g->kind->value,
            'title' => $g->title,
            'description' => $g->description ?? '',
            'target_value' => $g->target_value ?? '',
            'unit' => $g->unit ?? '',
        ])->values()->all();

        if ($this->goals === []) {
            $this->goals = [
                ['kind' => GoalKind::Main->value, 'title' => '', 'description' => '', 'target_value' => '', 'unit' => ''],
            ];
        }
    }

    public function nextStep(): void
    {
        $this->validateStep($this->step);
        $this->step = min(4, $this->step + 1);
    }

    public function prevStep(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function addGoal(): void
    {
        $this->goals[] = [
            'kind' => GoalKind::Secondary->value,
            'title' => '',
            'description' => '',
            'target_value' => '',
            'unit' => '',
        ];
    }

    public function removeGoal(int $index): void
    {
        if (count($this->goals) <= 1) {
            return;
        }
        unset($this->goals[$index]);
        $this->goals = array_values($this->goals);
    }

    public function save(EventService $service): void
    {
        $this->validateStep(1);
        $this->validateStep(2);
        $this->validateStep(3);

        $start = $this->start_date_jalali ? Jalali::parse($this->start_date_jalali)?->toDateString() : null;
        $end = $this->end_date_jalali ? Jalali::parse($this->end_date_jalali)?->toDateString() : null;

        $payload = [
            'title' => $this->title,
            'sport_type' => $this->sport_type ?: null,
            'event_type' => $this->event_type,
            'scale' => $this->scale,
            'main_goal_type' => $this->main_goal_type ?: null,
            'description' => $this->description ?: null,
            'start_date' => $start,
            'end_date' => $end,
            'venue_name' => $this->venue_name ?: null,
            'city' => $this->city ?: null,
            'province' => $this->province ?: null,
            'expected_participants' => $this->expected_participants,
            'expected_spectators' => $this->expected_spectators,
            'budget_estimate' => $this->budget_estimate,
            'organization_id' => Auth::user()->current_organization_id,
        ];

        $goals = collect($this->goals)
            ->filter(fn ($g) => filled($g['title'] ?? null))
            ->values()
            ->all();

        if ($this->event) {
            $event = $service->update($this->event, $payload, $goals);
            session()->flash('success', __('event.updated'));
        } else {
            $event = $service->create(Auth::user(), $payload, $goals);
            session()->flash('success', __('event.created'));
        }

        $this->redirect(route('events.show', $event), navigate: true);
    }

    private function validateStep(int $step): void
    {
        if ($step === 1) {
            $this->validate([
                'title' => ['required', 'string', 'min:3', 'max:200'],
                'sport_type' => ['nullable', 'string', 'max:100'],
                'event_type' => ['required', Rule::enum(EventType::class)],
                'scale' => ['required', Rule::enum(EventScale::class)],
            ]);
        }

        if ($step === 2) {
            $this->validate([
                'main_goal_type' => ['nullable', Rule::enum(MainGoalType::class)],
                'description' => ['nullable', 'string', 'max:5000'],
                'goals' => ['array'],
                'goals.*.title' => ['nullable', 'string', 'max:200'],
            ]);
        }

        if ($step === 3) {
            $this->validate([
                'start_date_jalali' => ['nullable', 'string'],
                'end_date_jalali' => ['nullable', 'string'],
                'venue_name' => ['nullable', 'string', 'max:200'],
                'city' => ['nullable', 'string', 'max:100'],
                'province' => ['nullable', 'string', 'max:100'],
                'expected_participants' => ['nullable', 'integer', 'min:0'],
                'expected_spectators' => ['nullable', 'integer', 'min:0'],
                'budget_estimate' => ['nullable', 'integer', 'min:0'],
            ]);

            $start = $this->start_date_jalali ? Jalali::parse($this->start_date_jalali) : null;
            $end = $this->end_date_jalali ? Jalali::parse($this->end_date_jalali) : null;

            if ($start && $end && $end->lt($start)) {
                $this->addError('end_date_jalali', __('event.end_before_start'));
            }
        }
    }

    public function render()
    {
        return view('livewire.events.event-form', [
            'eventTypes' => EventType::cases(),
            'scales' => EventScale::cases(),
            'goalTypes' => MainGoalType::cases(),
        ])->title($this->event ? __('event.edit_title') : __('event.create_title'));
    }
}
