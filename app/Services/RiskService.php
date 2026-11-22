<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EventRole;
use App\Enums\EventStatus;
use App\Enums\IncidentSeverity;
use App\Enums\RiskCategory;
use App\Enums\RiskLevel;
use App\Enums\RiskResponse;
use App\Enums\RiskStatus;
use App\Enums\TaskModule;
use App\Enums\TaskPhase;
use App\Enums\TaskPriority;
use App\Models\Event;
use App\Models\Incident;
use App\Models\Risk;
use App\Models\Task;
use App\Models\User;
use App\Support\LogsActivity;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Step 10 — ریسک و حادثه.
 *
 * قواعد:
 *  - امتیاز = احتمال (۱–۵) × اثر (۱–۵)؛ در مدل هنگام ذخیره محاسبه می‌شود.
 *  - کاربر عادی (`risk_basic`): ثبت ساده‌ی ریسک (فهرست مرتب‌شده بر اساس امتیاز) + وظیفه‌ی کنترلی.
 *  - کاربر سازمانی (`risk_full`): ماتریس ۵×۵، چک‌لیست‌های پیشنهادی، ثبت حادثه.
 *  - مسئول ریسک باید عضو غیر-بیننده‌ی همین رویداد باشد؛ وظیفه/ریسک/حادثه همیشه داخل همان رویداد.
 *  - مجوز در RiskPolicy است؛ این سرویس فقط قواعد کسب‌وکار و ویژگی‌های طرح را اعمال می‌کند.
 */
class RiskService
{
    use LogsActivity;

    public const FEATURE_BASIC = 'risk_basic';

    public const FEATURE_FULL = 'risk_full';

    public const MAX_TITLE_LENGTH = 160;

    public const MAX_TEXT_LENGTH = 2000;

    public function __construct(
        private EntitlementService $entitlement,
        private TaskService $tasks,
    ) {}

    // ------------------------------------------------------------ entitlement

    public function hasBasic(User $actor): bool
    {
        return $this->entitlement->can($actor, self::FEATURE_BASIC);
    }

    public function hasFull(User $actor): bool
    {
        return $this->entitlement->can($actor, self::FEATURE_FULL);
    }

    // ------------------------------------------------------------------ risks

    /**
     * @param  array<string, mixed>  $data  title, category, description, likelihood, impact, response,
     *                                      control_actions, owner_id, status, review_date
     *
     * @throws InvalidArgumentException
     */
    public function create(Event $event, User $actor, array $data): Risk
    {
        $this->assertWritable($event, $actor);

        $payload = $this->buildPayload($event, $data, creating: true);

        $risk = Risk::query()->create($payload + [
            'event_id' => $event->id,
            'created_by' => $actor->id,
        ]);

        self::logActivity('risk.created', $risk, $event->id, [
            'title' => $risk->title,
            'score' => $risk->score,
        ]);

        return $risk->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidArgumentException
     */
    public function update(Risk $risk, User $actor, array $data): Risk
    {
        $this->assertWritable($risk->event, $actor);

        $payload = $this->buildPayload($risk->event, $data, creating: false);

        if ($payload === []) {
            return $risk;
        }

        $risk->fill($payload)->save();

        $changes = array_values(array_diff(array_keys($risk->getChanges()), ['updated_at']));
        if ($changes !== []) {
            self::logActivity('risk.updated', $risk, $risk->event_id, ['changes' => $changes, 'score' => $risk->score]);
        }

        return $risk->refresh();
    }

    public function setStatus(Risk $risk, User $actor, RiskStatus $status): Risk
    {
        $this->assertWritable($risk->event, $actor);

        if ($risk->status === $status) {
            return $risk;
        }

        $from = $risk->status;
        $risk->update(['status' => $status]);

        self::logActivity('risk.status_changed', $risk, $risk->event_id, [
            'from' => $from?->value,
            'to' => $status->value,
        ]);

        return $risk->refresh();
    }

    public function delete(Risk $risk, User $actor): void
    {
        $this->assertWritable($risk->event, $actor);

        self::logActivity('risk.deleted', $risk, $risk->event_id, ['title' => $risk->title]);

        // وظیفه‌های کنترلی حذف نمی‌شوند؛ فقط پیوندشان با این ریسک برداشته می‌شود
        DB::transaction(function () use ($risk) {
            $risk->tasks()->detach();
            $risk->delete();
        });
    }

    /**
     * Risks of an event, highest score first.
     *
     * @param  array{status?: ?string, category?: ?string, cell?: ?string}  $filters
     *                                                                                 status: 'active' (default) | 'all' | a RiskStatus value
     *                                                                                 cell:   "likelihood-impact" (matrix cell)
     * @return Collection<int, Risk>
     */
    public function register(Event $event, array $filters = []): Collection
    {
        $query = Risk::query()->forEvent($event)->with(['owner', 'tasks'])->byScore();

        $status = $filters['status'] ?? 'active';
        if ($status === 'active' || $status === null || $status === '') {
            $query->active();
        } elseif ($status !== 'all' && RiskStatus::tryFrom((string) $status) !== null) {
            $query->where('status', $status);
        }

        if (! empty($filters['category']) && RiskCategory::tryFrom((string) $filters['category']) !== null) {
            $query->where('category', $filters['category']);
        }

        if (! empty($filters['cell']) && preg_match('/^([1-5])-([1-5])$/', (string) $filters['cell'], $m) === 1) {
            $query->where('likelihood', (int) $m[1])->where('impact', (int) $m[2]);
        }

        return $query->get();
    }

    // ----------------------------------------------------------------- matrix

    /**
     * Active risks grouped by matrix cell. Key = "likelihood-impact".
     *
     * @return array<string, list<Risk>>
     */
    public function matrix(Event $event): array
    {
        $cells = [];

        Risk::query()->forEvent($event)->active()->byScore()->get()
            ->each(function (Risk $risk) use (&$cells) {
                $cells[$risk->likelihood.'-'.$risk->impact][] = $risk;
            });

        return $cells;
    }

    /**
     * @return array{total: int, by_level: array<string, int>, review_overdue: int, review_soon: int, occurred: int, top: Collection<int, Risk>}
     */
    public function summary(Event $event): array
    {
        $active = Risk::query()->forEvent($event)->active()->byScore()->get();

        $byLevel = [];
        foreach (RiskLevel::cases() as $level) {
            $byLevel[$level->value] = 0;
        }
        foreach ($active as $risk) {
            $byLevel[$risk->level()->value]++;
        }

        $today = now()->startOfDay();
        $soonUntil = $today->copy()->addDays((int) config('risk.review_soon_days', 7));

        return [
            'total' => $active->count(),
            'by_level' => $byLevel,
            'review_overdue' => $active->filter(fn (Risk $r) => $r->isReviewOverdue())->count(),
            'review_soon' => $active->filter(fn (Risk $r) => $r->review_date !== null
                && $r->review_date->gte($today)
                && $r->review_date->lte($soonUntil))->count(),
            'occurred' => Risk::query()->forEvent($event)->where('status', RiskStatus::Occurred->value)->count(),
            'top' => $active->take(5)->values(),
        ];
    }

    // -------------------------------------------------------------- templates

    /**
     * Fixed checklist templates (config/risk.php). Always "suggestions": a human
     * reviews every created risk.
     *
     * @return array<string, array{title: string, items: list<array<string, mixed>>}>
     */
    public function templates(): array
    {
        return (array) config('risk.templates', []);
    }

    /**
     * Creates risks from a template. Items already added to this event
     * (same template_key, not deleted) are skipped.
     *
     * @param  list<string>|null  $itemKeys  null = every item of the template
     * @return Collection<int, Risk> the newly created risks
     *
     * @throws InvalidArgumentException
     */
    public function applyTemplate(Event $event, User $actor, string $templateKey, ?array $itemKeys = null): Collection
    {
        $this->assertWritable($event, $actor);
        $this->assertFull($actor);

        $template = $this->templates()[$templateKey] ?? null;
        if ($template === null) {
            throw new InvalidArgumentException(__('risk.template_unknown'));
        }

        $existing = Risk::query()->forEvent($event)->whereNotNull('template_key')->pluck('template_key')->all();
        $created = collect();

        DB::transaction(function () use ($event, $actor, $template, $itemKeys, $existing, &$created) {
            foreach ($template['items'] as $item) {
                if ($itemKeys !== null && ! in_array($item['key'], $itemKeys, true)) {
                    continue;
                }
                if (in_array($item['key'], $existing, true)) {
                    continue;
                }

                $risk = Risk::query()->create([
                    'event_id' => $event->id,
                    'created_by' => $actor->id,
                    'title' => $item['title'],
                    'category' => $item['category'],
                    'description' => $item['description'] ?? null,
                    'likelihood' => $item['likelihood'],
                    'impact' => $item['impact'],
                    'response' => $item['response'] ?? RiskResponse::Reduce->value,
                    'control_actions' => $item['control_actions'] ?? null,
                    'status' => RiskStatus::Open->value,
                    'template_key' => $item['key'],
                ]);

                $created->push($risk);
            }

            if ($created->isNotEmpty()) {
                self::logActivity('risk.template_applied', null, $event->id, [
                    'items' => $created->pluck('template_key')->all(),
                ]);
            }
        });

        return $created;
    }

    // ---------------------------------------------------------- control tasks

    /**
     * Links an existing task of the SAME event as a control task.
     *
     * @throws InvalidArgumentException
     */
    public function linkTask(Risk $risk, User $actor, Task $task): void
    {
        $this->assertWritable($risk->event, $actor);

        if ((int) $task->event_id !== (int) $risk->event_id || $task->trashed()) {
            throw new InvalidArgumentException(__('risk.task_invalid'));
        }

        if ($risk->tasks()->whereKey($task->id)->exists()) {
            throw new InvalidArgumentException(__('risk.task_already_linked'));
        }

        $risk->tasks()->attach($task->id);

        self::logActivity('risk.task_linked', $risk, $risk->event_id, ['task_id' => $task->id]);
    }

    public function unlinkTask(Risk $risk, User $actor, Task $task): void
    {
        $this->assertWritable($risk->event, $actor);

        if ($risk->tasks()->detach($task->id) > 0) {
            self::logActivity('risk.task_unlinked', $risk, $risk->event_id, ['task_id' => $task->id]);
        }
    }

    /**
     * Creates a task (module = risk) for this risk and links it. The caller
     * authorizes TaskPolicy@create. Plan limits (tasks_per_event) still apply.
     *
     * @param  array<string, mixed>  $data  title?, committee_id?, assignee_id?, due_at?
     *
     * @throws InvalidArgumentException
     */
    public function createControlTask(Risk $risk, User $actor, array $data = []): Task
    {
        $this->assertWritable($risk->event, $actor);

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            $title = mb_substr(__('risk.control_task_prefix').' '.$risk->title, 0, TaskService::MAX_TITLE_LENGTH);
        }

        $assigneeId = $data['assignee_id'] ?? $this->validOwnerId($risk->event, $risk->owner_id);

        return DB::transaction(function () use ($risk, $actor, $data, $title, $assigneeId) {
            $task = $this->tasks->create($risk->event, $actor, [
                'title' => $title,
                'description' => $risk->control_actions,
                'module' => TaskModule::Risk,
                'phase' => TaskPhase::Pre,
                'priority' => match ($risk->level()) {
                    RiskLevel::Critical => TaskPriority::Urgent,
                    RiskLevel::High => TaskPriority::High,
                    default => TaskPriority::Medium,
                },
                'committee_id' => $data['committee_id'] ?? null,
                'assignee_id' => $assigneeId,
                'due_at' => $data['due_at'] ?? null,
            ]);

            $risk->tasks()->attach($task->id);

            self::logActivity('risk.task_linked', $risk, $risk->event_id, [
                'task_id' => $task->id,
                'created' => true,
            ]);

            return $task;
        });
    }

    // -------------------------------------------------------------- incidents

    /**
     * @return Collection<int, Incident>
     */
    public function incidents(Event $event): Collection
    {
        return Incident::query()->forEvent($event)->with(['risk', 'reporter'])
            ->orderByDesc('occurred_at')->orderByDesc('id')->get();
    }

    /**
     * @param  array<string, mixed>  $data  title, severity, occurred_at, description, risk_id
     *
     * @throws InvalidArgumentException
     */
    public function recordIncident(Event $event, User $actor, array $data): Incident
    {
        $this->assertWritable($event, $actor);
        $this->assertFull($actor);

        $incident = Incident::query()->create([
            'event_id' => $event->id,
            'risk_id' => $this->resolveRiskId($event, $data['risk_id'] ?? null),
            'title' => $this->validTitle($data['title'] ?? '', 'risk.incident_title_required'),
            'description' => $this->nullableText($data['description'] ?? null),
            'severity' => $this->enumCase(IncidentSeverity::class, $data['severity'] ?? null) ?? IncidentSeverity::Medium,
            'reported_by' => $actor->id,
            'occurred_at' => $this->parseDateTime($data['occurred_at'] ?? null) ?? now(),
        ]);

        self::logActivity('incident.created', $incident, $event->id, [
            'title' => $incident->title,
            'severity' => $incident->severity->value,
        ]);

        return $incident->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateIncident(Incident $incident, User $actor, array $data): Incident
    {
        $event = $incident->event;
        $this->assertWritable($event, $actor);
        $this->assertFull($actor);

        $payload = [];

        if (array_key_exists('title', $data)) {
            $payload['title'] = $this->validTitle($data['title'] ?? '', 'risk.incident_title_required');
        }
        if (array_key_exists('description', $data)) {
            $payload['description'] = $this->nullableText($data['description']);
        }
        if (array_key_exists('severity', $data)) {
            $payload['severity'] = $this->enumCase(IncidentSeverity::class, $data['severity'])
                ?? throw new InvalidArgumentException(__('risk.invalid_value'));
        }
        if (array_key_exists('risk_id', $data)) {
            $payload['risk_id'] = $this->resolveRiskId($event, $data['risk_id']);
        }
        if (array_key_exists('occurred_at', $data)) {
            $payload['occurred_at'] = $this->parseDateTime($data['occurred_at']) ?? $incident->occurred_at;
        }

        if ($payload !== []) {
            $incident->update($payload);

            self::logActivity('incident.updated', $incident, $incident->event_id, [
                'changes' => array_values(array_diff(array_keys($incident->getChanges()), ['updated_at'])),
            ]);
        }

        return $incident->refresh();
    }

    public function resolveIncident(Incident $incident, User $actor, string $resolution): Incident
    {
        $this->assertWritable($incident->event, $actor);
        $this->assertFull($actor);

        $resolution = trim($resolution);
        if ($resolution === '') {
            throw new InvalidArgumentException(__('risk.resolution_required'));
        }
        if (mb_strlen($resolution) > self::MAX_TEXT_LENGTH) {
            throw new InvalidArgumentException(__('risk.text_too_long', ['max' => self::MAX_TEXT_LENGTH]));
        }

        $incident->update([
            'resolution' => $resolution,
            'resolved_at' => $incident->resolved_at ?? now(),
        ]);

        self::logActivity('incident.resolved', $incident, $incident->event_id, []);

        return $incident->refresh();
    }

    public function deleteIncident(Incident $incident, User $actor): void
    {
        $this->assertWritable($incident->event, $actor);
        $this->assertFull($actor);

        self::logActivity('incident.deleted', $incident, $incident->event_id, ['title' => $incident->title]);

        $incident->delete();
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function buildPayload(Event $event, array $data, bool $creating): array
    {
        $payload = [];

        if ($creating || array_key_exists('title', $data)) {
            $payload['title'] = $this->validTitle($data['title'] ?? '', 'risk.title_required');
        }

        if ($creating || array_key_exists('category', $data)) {
            $payload['category'] = $this->enumCase(RiskCategory::class, $data['category'] ?? null)
                ?? throw new InvalidArgumentException(__('risk.invalid_value'));
        }

        foreach (['likelihood', 'impact'] as $key) {
            if ($creating || array_key_exists($key, $data)) {
                $payload[$key] = $this->rating($data[$key] ?? null);
            }
        }

        if (array_key_exists('response', $data) || $creating) {
            $payload['response'] = $this->enumCase(RiskResponse::class, $data['response'] ?? null)
                ?? ($creating ? RiskResponse::Reduce : throw new InvalidArgumentException(__('risk.invalid_value')));
        }

        if (array_key_exists('status', $data) || $creating) {
            $payload['status'] = $this->enumCase(RiskStatus::class, $data['status'] ?? null)
                ?? ($creating ? RiskStatus::Open : throw new InvalidArgumentException(__('risk.invalid_value')));
        }

        if (array_key_exists('description', $data)) {
            $payload['description'] = $this->nullableText($data['description']);
        }
        if (array_key_exists('control_actions', $data)) {
            $payload['control_actions'] = $this->nullableText($data['control_actions']);
        }
        if (array_key_exists('owner_id', $data)) {
            $payload['owner_id'] = $this->resolveOwnerId($event, $data['owner_id']);
        }
        if (array_key_exists('review_date', $data)) {
            $payload['review_date'] = $this->parseDate($data['review_date'])?->toDateString();
        }

        return $payload;
    }

    private function validTitle(mixed $title, string $requiredKey): string
    {
        $title = trim((string) $title);

        if ($title === '') {
            throw new InvalidArgumentException(__($requiredKey));
        }
        if (mb_strlen($title) > self::MAX_TITLE_LENGTH) {
            throw new InvalidArgumentException(__('risk.title_too_long', ['max' => self::MAX_TITLE_LENGTH]));
        }

        return $title;
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) > self::MAX_TEXT_LENGTH) {
            throw new InvalidArgumentException(__('risk.text_too_long', ['max' => self::MAX_TEXT_LENGTH]));
        }

        return $value;
    }

    private function rating(mixed $value): int
    {
        if (is_string($value) && preg_match('/^\d+$/', trim($value)) === 1) {
            $value = (int) $value;
        }

        if (! is_int($value) || $value < 1 || $value > 5) {
            throw new InvalidArgumentException(__('risk.rating_invalid'));
        }

        return $value;
    }

    /**
     * @template T of \BackedEnum
     *
     * @param  class-string<T>  $enum
     * @return T|null
     */
    private function enumCase(string $enum, mixed $value): ?\BackedEnum
    {
        if ($value instanceof $enum) {
            return $value;
        }

        if ($value === null || $value === '') {
            return null;
        }

        return $enum::tryFrom((string) $value)
            ?? throw new InvalidArgumentException(__('risk.invalid_value'));
    }

    /**
     * Owner must be a non-viewer member of THIS event (or null).
     */
    private function resolveOwnerId(Event $event, mixed $ownerId): ?int
    {
        if ($ownerId === null || $ownerId === '') {
            return null;
        }

        $owner = $this->validOwnerId($event, (int) $ownerId);

        if ($owner === null) {
            throw new InvalidArgumentException(__('risk.owner_invalid'));
        }

        return $owner;
    }

    private function validOwnerId(Event $event, mixed $userId): ?int
    {
        if ($userId === null || $userId === '') {
            return null;
        }

        $user = User::query()->find((int) $userId);
        if ($user === null) {
            return null;
        }

        $role = $event->roleOf($user);

        return ($role !== null && $role !== EventRole::Viewer) ? $user->id : null;
    }

    private function resolveRiskId(Event $event, mixed $riskId): ?int
    {
        if ($riskId === null || $riskId === '') {
            return null;
        }

        $risk = Risk::query()->forEvent($event)->whereKey((int) $riskId)->first();

        if ($risk === null) {
            throw new InvalidArgumentException(__('risk.risk_invalid'));
        }

        return $risk->id;
    }

    private function parseDate(mixed $value): ?Carbon
    {
        $carbon = $this->parseDateTime($value);

        return $carbon?->startOfDay();
    }

    private function parseDateTime(mixed $value): ?Carbon
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
            throw new InvalidArgumentException(__('risk.date_invalid'));
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    private function assertWritable(Event $event, User $actor): void
    {
        if (! $this->hasBasic($actor)) {
            throw new InvalidArgumentException(__('risk.feature_unavailable'));
        }

        if (in_array($event->status, [EventStatus::Archived, EventStatus::Cancelled], true)) {
            throw new InvalidArgumentException(__('risk.event_closed'));
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    private function assertFull(User $actor): void
    {
        if (! $this->hasFull($actor)) {
            throw new InvalidArgumentException(__('risk.full_required'));
        }
    }
}
