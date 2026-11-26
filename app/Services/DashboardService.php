<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\IncidentSeverity;
use App\Enums\RiskLevel;
use App\Enums\TaskPhase;
use App\Enums\TaskStatus;
use App\Models\ActivityLog;
use App\Models\BudgetLine;
use App\Models\Event;
use App\Models\EventAnnouncement;
use App\Models\EventInvitation;
use App\Models\Incident;
use App\Models\Risk;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Step 13 — read-only aggregates for the dashboards.
 *
 * Every section honours the same Policy rules as its own tab:
 *  - budget numbers  ← BudgetLinePolicy@viewSummary
 *  - risks/incidents ← RiskPolicy@viewRegister
 * so a viewer never sees through the dashboard what the budget tab hides.
 */
class DashboardService
{
    public function __construct(
        private TaskService $tasks,
        private BudgetService $budget,
        private RiskService $risks,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forEvent(Event $event, User $user): array
    {
        $progress = $this->tasks->progressForEvent($event);

        $overdueByCommittee = Task::query()
            ->where('event_id', $event->id)
            ->overdue()
            ->selectRaw('committee_id, count(*) as total')
            ->groupBy('committee_id')
            ->pluck('total', 'committee_id');

        $committees = $event->committees()->get()->map(fn ($committee) => [
            'committee' => $committee,
            'progress' => $this->tasks->progressForCommittee($committee),
            'overdue' => (int) ($overdueByCommittee[$committee->id] ?? 0),
        ]);

        $canBudget = $user->can('viewSummary', [BudgetLine::class, $event]);
        $canRisk = $user->can('viewRegister', [Risk::class, $event]);

        $activity = ActivityLog::query()
            ->with('user')
            ->where('event_id', $event->id)
            ->when(! $canBudget, fn ($q) => $q->where('action', 'not like', 'budget.%'))
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        return [
            'progress' => $progress,
            'overdue_total' => (int) $overdueByCommittee->sum(),
            'committees' => $committees,
            'committees_behind' => $committees->filter(fn (array $row) => $row['overdue'] > 0)
                ->sortByDesc('overdue')->values(),
            'can_budget' => $canBudget,
            'budget' => $canBudget ? $this->budget->totals($event) : null,
            'can_risk' => $canRisk,
            'risk' => $canRisk ? $this->riskBlock($event) : null,
            'countdown' => $this->countdown($event),
            'activity' => $activity,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function controlCenter(Event $event): array
    {
        $during = Task::query()
            ->where('event_id', $event->id)
            ->where('phase', TaskPhase::During->value)
            ->whereDoesntHave('subtasks');

        $counts = [];
        foreach (TaskStatus::cases() as $status) {
            $counts[$status->value] = (clone $during)->where('status', $status->value)->count();
        }

        $open = (clone $during)->open()
            ->with(['assignee', 'committee'])
            ->orderByRaw('due_at IS NULL')
            ->orderBy('due_at')
            ->limit(15)
            ->get();

        $incidents = Incident::query()->forEvent($event)
            ->with('reporter')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');

        return [
            'task_counts' => $counts,
            'task_total' => array_sum($counts),
            'open_tasks' => $open,
            'overdue_during' => (clone $during)->overdue()->count(),
            'unresolved_incidents' => (clone $incidents)->whereNull('resolved_at')->limit(6)->get(),
            'unresolved_count' => Incident::query()->forEvent($event)->whereNull('resolved_at')->count(),
            'severe_unresolved' => Incident::query()->forEvent($event)->whereNull('resolved_at')
                ->whereIn('severity', [IncidentSeverity::High->value, IncidentSeverity::Critical->value])->count(),
            'announcements' => EventAnnouncement::query()->with('author')
                ->where('event_id', $event->id)->orderByDesc('id')->limit(5)->get(),
            'countdown' => $this->countdown($event),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user): array
    {
        $open = Task::query()
            ->where('assignee_id', $user->id)
            ->open()
            ->whereHas('event', fn ($q) => $q->active());

        $invitations = EventInvitation::query()
            ->with(['event', 'committee', 'inviter'])
            ->where('phone', $user->phone)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->whereHas('event')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        return [
            'open_tasks' => (clone $open)->count(),
            'overdue_tasks' => (clone $open)->where('due_at', '<', now())->count(),
            'invitations' => $invitations,
            'unread_notifications' => $user->unreadNotifications()->count(),
        ];
    }

    /**
     * @return array{days: int|null, state: string}
     *   state: none | upcoming | today | running | finished
     */
    public function countdown(Event $event): array
    {
        if ($event->start_date === null) {
            return ['days' => null, 'state' => 'none'];
        }

        // مقایسه‌ی «روز» در منطقه‌ی تهران (نه لحظه)، مستقل از timezone برنامه
        $tz = 'Asia/Tehran';
        $today = now($tz)->startOfDay();
        $start = Carbon::parse($event->start_date->toDateString(), $tz)->startOfDay();
        $end = Carbon::parse(($event->end_date ?? $event->start_date)->toDateString(), $tz)->startOfDay();

        if ($today->lt($start)) {
            return ['days' => (int) $today->diffInDays($start), 'state' => 'upcoming'];
        }

        if ($today->gt($end)) {
            return ['days' => (int) $end->diffInDays($today), 'state' => 'finished'];
        }

        return ['days' => 0, 'state' => $today->equalTo($start) ? 'today' : 'running'];
    }

    /**
     * @return array{summary: array<string, mixed>, critical: Collection<int, Risk>}
     */
    private function riskBlock(Event $event): array
    {
        $summary = $this->risks->summary($event);

        return [
            'summary' => $summary,
            'critical' => $summary['top']
                ->filter(fn (Risk $risk) => $risk->level() === RiskLevel::Critical)
                ->values(),
        ];
    }
}
