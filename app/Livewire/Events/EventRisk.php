<?php

declare(strict_types=1);

namespace App\Livewire\Events;

use App\Enums\EventRole;
use App\Enums\EventStatus;
use App\Enums\IncidentSeverity;
use App\Enums\RiskCategory;
use App\Enums\RiskResponse;
use App\Enums\RiskStatus;
use App\Exceptions\LimitExceededException;
use App\Models\Event;
use App\Models\Incident;
use App\Models\Risk;
use App\Models\Task;
use App\Services\RiskService;
use App\Support\Jalali;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Component;

/**
 * تب «ریسک» در هاب رویداد (Step 10).
 *
 * دسترسی (RiskPolicy):
 *  - viewRegister: دیدن ثبت ریسک‌ها (هر کسی که رویداد را می‌بیند)
 *  - manage: ریسک، قالب‌ها، وظیفه‌ی کنترلی، ویرایش/حل/حذف حادثه
 *  - reportIncident: ثبت حادثه‌ی جدید (مدیریت + رئیس کمیته + عضو)
 * هر اکشن Livewire جداگانه authorize می‌شود و هر شناسه فقط داخل همین رویداد پیدا می‌شود.
 * طرح حساب: عادی = فقط ثبت ساده؛ سازمانی = ماتریس، چک‌لیست‌ها و حادثه.
 */
class EventRisk extends Component
{
    public Event $event;

    public string $section = 'register';

    // ---- فیلترها
    public string $filterStatus = 'active';

    public string $filterCategory = '';

    public string $filterCell = '';

    // ---- فرم ریسک
    public bool $showRiskForm = false;

    public ?int $editingRiskId = null;

    public string $rTitle = '';

    public string $rCategory = 'safety';

    public string $rDescription = '';

    public string $rLikelihood = '3';

    public string $rImpact = '3';

    public string $rResponse = 'reduce';

    public string $rControl = '';

    public string $rOwnerId = '';

    public string $rStatus = 'open';

    public string $rReview = '';

    // ---- جزئیات ریسک / وظایف کنترلی
    public ?int $expandedRiskId = null;

    public string $ctTitle = '';

    public string $ctTaskId = '';

    // ---- فرم حادثه
    public bool $showIncidentForm = false;

    public ?int $editingIncidentId = null;

    public string $iTitle = '';

    public string $iSeverity = 'medium';

    public string $iDate = '';

    public string $iTime = '';

    public string $iRiskId = '';

    public string $iDescription = '';

    public ?int $resolvingIncidentId = null;

    public string $resolutionText = '';

    public ?string $error = null;

    public ?string $notice = null;

    public function mount(Event $event): void
    {
        $this->authorize('viewRegister', [Risk::class, $event]);
        $this->event = $event;
    }

    // ------------------------------------------------------------ navigation

    public function setSection(string $section): void
    {
        $this->section = in_array($section, $this->allowedSections(), true) ? $section : 'register';
        $this->clearMessages();
    }

    public function setCell(string $cell): void
    {
        $valid = preg_match('/^[1-5]-[1-5]$/', $cell) === 1;

        $this->filterCell = ($valid && $this->filterCell !== $cell) ? $cell : '';
        $this->section = 'register';
    }

    public function clearCell(): void
    {
        $this->filterCell = '';
    }

    // ------------------------------------------------------------------ risks

    public function openRiskForm(): void
    {
        $this->authorize('manage', [Risk::class, $this->event]);
        $this->resetRiskForm();
        $this->section = 'register';
        $this->showRiskForm = true;
    }

    public function cancelRiskForm(): void
    {
        $this->resetRiskForm();
    }

    public function editRisk(int $id): void
    {
        $this->authorize('manage', [Risk::class, $this->event]);

        $risk = $this->findRisk($id);

        $this->clearMessages();
        $this->editingRiskId = $risk->id;
        $this->rTitle = $risk->title;
        $this->rCategory = $risk->category->value;
        $this->rDescription = (string) $risk->description;
        $this->rLikelihood = (string) $risk->likelihood;
        $this->rImpact = (string) $risk->impact;
        $this->rResponse = $risk->response->value;
        $this->rControl = (string) $risk->control_actions;
        $this->rOwnerId = (string) ($risk->owner_id ?? '');
        $this->rStatus = $risk->status->value;
        $this->rReview = $risk->review_date !== null ? fa_num((string) Jalali::format($risk->review_date)) : '';
        $this->showRiskForm = true;
    }

    public function saveRisk(RiskService $service): void
    {
        $this->authorize('manage', [Risk::class, $this->event]);
        $this->clearMessages();

        $this->validate([
            'rTitle' => ['required', 'string', 'max:'.RiskService::MAX_TITLE_LENGTH],
            'rCategory' => ['required', 'in:'.implode(',', array_column(RiskCategory::cases(), 'value'))],
            'rDescription' => ['nullable', 'string', 'max:'.RiskService::MAX_TEXT_LENGTH],
            'rLikelihood' => ['required', 'in:1,2,3,4,5'],
            'rImpact' => ['required', 'in:1,2,3,4,5'],
            'rResponse' => ['required', 'in:'.implode(',', array_column(RiskResponse::cases(), 'value'))],
            'rControl' => ['nullable', 'string', 'max:'.RiskService::MAX_TEXT_LENGTH],
            'rOwnerId' => ['nullable', 'integer'],
            'rStatus' => ['required', 'in:'.implode(',', array_column(RiskStatus::cases(), 'value'))],
            'rReview' => ['nullable', 'string', 'max:20'],
        ], [], [
            'rTitle' => __('risk.fields.title'),
            'rCategory' => __('risk.fields.category'),
            'rDescription' => __('risk.fields.description'),
            'rLikelihood' => __('risk.fields.likelihood'),
            'rImpact' => __('risk.fields.impact'),
            'rResponse' => __('risk.fields.response'),
            'rControl' => __('risk.fields.control_actions'),
            'rOwnerId' => __('risk.fields.owner'),
            'rStatus' => __('risk.fields.status'),
            'rReview' => __('risk.fields.review_date'),
        ]);

        $review = null;
        if (trim($this->rReview) !== '') {
            $review = Jalali::parse($this->rReview);

            if ($review === null) {
                $this->addError('rReview', __('risk.date_invalid'));

                return;
            }
        }

        $data = [
            'title' => $this->rTitle,
            'category' => $this->rCategory,
            'description' => $this->rDescription,
            'likelihood' => (int) $this->rLikelihood,
            'impact' => (int) $this->rImpact,
            'response' => $this->rResponse,
            'control_actions' => $this->rControl,
            'owner_id' => $this->rOwnerId !== '' ? (int) $this->rOwnerId : null,
            'status' => $this->rStatus,
            'review_date' => $review,
        ];

        try {
            if ($this->editingRiskId !== null) {
                $service->update($this->findRisk($this->editingRiskId), Auth::user(), $data);
                $this->notice = __('risk.risk_updated');
            } else {
                $service->create($this->event, Auth::user(), $data);
                $this->notice = __('risk.risk_created');
            }

            $this->resetRiskForm();
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function changeStatus(int $id, string $status, RiskService $service): void
    {
        $this->authorize('manage', [Risk::class, $this->event]);
        $this->clearMessages();

        $case = RiskStatus::tryFrom($status);
        if ($case === null) {
            $this->error = __('risk.invalid_value');

            return;
        }

        try {
            $service->setStatus($this->findRisk($id), Auth::user(), $case);
            $this->notice = __('risk.status_changed');
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function deleteRisk(int $id, RiskService $service): void
    {
        $this->authorize('manage', [Risk::class, $this->event]);
        $this->clearMessages();

        try {
            $service->delete($this->findRisk($id), Auth::user());

            if ($this->expandedRiskId === $id) {
                $this->expandedRiskId = null;
            }
            $this->notice = __('risk.risk_deleted');
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function toggleRisk(int $id): void
    {
        // فقط داخل همین رویداد پیدا می‌شود
        $risk = $this->findRisk($id);

        $this->expandedRiskId = $this->expandedRiskId === $risk->id ? null : $risk->id;
        $this->ctTitle = '';
        $this->ctTaskId = '';
        $this->clearMessages();
    }

    // ---------------------------------------------------------- control tasks

    public function createControlTask(int $riskId, RiskService $service): void
    {
        $this->authorize('manage', [Risk::class, $this->event]);
        $this->authorize('create', [Task::class, $this->event]);
        $this->clearMessages();

        $this->validate([
            'ctTitle' => ['nullable', 'string', 'max:200'],
        ], [], ['ctTitle' => __('risk.control_task_title')]);

        try {
            $service->createControlTask($this->findRisk($riskId), Auth::user(), ['title' => $this->ctTitle]);
            $this->ctTitle = '';
            $this->notice = __('risk.task_created');
        } catch (InvalidArgumentException|LimitExceededException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function linkTask(int $riskId, RiskService $service): void
    {
        $this->authorize('manage', [Risk::class, $this->event]);
        $this->clearMessages();

        if (! ctype_digit($this->ctTaskId)) {
            $this->error = __('risk.task_invalid');

            return;
        }

        try {
            $task = $this->event->tasks()->whereKey((int) $this->ctTaskId)->first();

            if ($task === null) {
                throw new InvalidArgumentException(__('risk.task_invalid'));
            }

            $service->linkTask($this->findRisk($riskId), Auth::user(), $task);
            $this->ctTaskId = '';
            $this->notice = __('risk.task_linked');
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function unlinkTask(int $riskId, int $taskId, RiskService $service): void
    {
        $this->authorize('manage', [Risk::class, $this->event]);
        $this->clearMessages();

        try {
            $task = $this->event->tasks()->whereKey($taskId)->firstOrFail();
            $service->unlinkTask($this->findRisk($riskId), Auth::user(), $task);
            $this->notice = __('risk.task_unlinked');
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    // -------------------------------------------------------------- templates

    public function applyTemplate(string $key, RiskService $service): void
    {
        $this->runTemplate($key, null, $service);
    }

    public function applyTemplateItem(string $key, string $itemKey, RiskService $service): void
    {
        $this->runTemplate($key, [$itemKey], $service);
    }

    /**
     * @param  list<string>|null  $itemKeys
     */
    private function runTemplate(string $key, ?array $itemKeys, RiskService $service): void
    {
        $this->authorize('manage', [Risk::class, $this->event]);
        $this->clearMessages();

        try {
            $created = $service->applyTemplate($this->event, Auth::user(), $key, $itemKeys);

            $this->notice = $created->isEmpty()
                ? __('risk.template_nothing_new')
                : __('risk.template_applied', ['count' => fa_num($created->count())]);
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    // -------------------------------------------------------------- incidents

    public function openIncidentForm(): void
    {
        $this->authorize('reportIncident', [Risk::class, $this->event]);
        $this->resetIncidentForm();
        $this->section = 'incidents';
        $this->iDate = fa_num(Jalali::today());
        $this->iTime = fa_num(now()->format('H:i'));
        $this->showIncidentForm = true;
    }

    public function cancelIncidentForm(): void
    {
        $this->resetIncidentForm();
    }

    public function editIncident(int $id): void
    {
        $this->authorize('manage', [Risk::class, $this->event]);

        $incident = $this->findIncident($id);

        $this->clearMessages();
        $this->editingIncidentId = $incident->id;
        $this->iTitle = $incident->title;
        $this->iSeverity = $incident->severity->value;
        $this->iDate = fa_num((string) Jalali::format($incident->occurred_at));
        $this->iTime = fa_num($incident->occurred_at->format('H:i'));
        $this->iRiskId = (string) ($incident->risk_id ?? '');
        $this->iDescription = (string) $incident->description;
        $this->showIncidentForm = true;
    }

    public function saveIncident(RiskService $service): void
    {
        $this->authorize($this->editingIncidentId !== null ? 'manage' : 'reportIncident', [Risk::class, $this->event]);
        $this->clearMessages();

        $this->validate([
            'iTitle' => ['required', 'string', 'max:'.RiskService::MAX_TITLE_LENGTH],
            'iSeverity' => ['required', 'in:'.implode(',', array_column(IncidentSeverity::cases(), 'value'))],
            'iDate' => ['required', 'string', 'max:20'],
            'iTime' => ['required', 'string', 'max:5'],
            'iRiskId' => ['nullable', 'integer'],
            'iDescription' => ['nullable', 'string', 'max:'.RiskService::MAX_TEXT_LENGTH],
        ], [], [
            'iTitle' => __('risk.fields.incident_title'),
            'iSeverity' => __('risk.fields.severity'),
            'iDate' => __('risk.fields.occurred_date'),
            'iTime' => __('risk.fields.occurred_time'),
            'iRiskId' => __('risk.fields.related_risk'),
            'iDescription' => __('risk.fields.incident_description'),
        ]);

        $occurredAt = $this->parseOccurredAt();

        if ($occurredAt === null) {
            $this->addError('iDate', __('risk.date_invalid'));

            return;
        }

        $data = [
            'title' => $this->iTitle,
            'severity' => $this->iSeverity,
            'occurred_at' => $occurredAt,
            'risk_id' => $this->iRiskId !== '' ? (int) $this->iRiskId : null,
            'description' => $this->iDescription,
        ];

        try {
            if ($this->editingIncidentId !== null) {
                $service->updateIncident($this->findIncident($this->editingIncidentId), Auth::user(), $data);
                $this->notice = __('risk.incident_updated');
            } else {
                $service->recordIncident($this->event, Auth::user(), $data);
                $this->notice = __('risk.incident_created');
            }

            $this->resetIncidentForm();
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function startResolve(int $id): void
    {
        $this->authorize('manage', [Risk::class, $this->event]);

        $incident = $this->findIncident($id);

        $this->clearMessages();
        $this->resolvingIncidentId = $incident->id;
        $this->resolutionText = (string) $incident->resolution;
    }

    public function cancelResolve(): void
    {
        $this->resolvingIncidentId = null;
        $this->resolutionText = '';
    }

    public function saveResolution(RiskService $service): void
    {
        $this->authorize('manage', [Risk::class, $this->event]);
        $this->clearMessages();

        if ($this->resolvingIncidentId === null) {
            return;
        }

        try {
            $service->resolveIncident($this->findIncident($this->resolvingIncidentId), Auth::user(), $this->resolutionText);
            $this->cancelResolve();
            $this->notice = __('risk.incident_resolved');
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function deleteIncident(int $id, RiskService $service): void
    {
        $this->authorize('manage', [Risk::class, $this->event]);
        $this->clearMessages();

        try {
            $service->deleteIncident($this->findIncident($id), Auth::user());
            $this->notice = __('risk.incident_deleted');
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    // ----------------------------------------------------------------- render

    public function render(RiskService $service)
    {
        $user = Auth::user();
        $full = $service->hasFull($user);
        $basic = $service->hasBasic($user);

        // اگر طرح تغییر کرده باشد، بخش نامجاز به ثبت ساده برمی‌گردد
        if (! in_array($this->section, $this->allowedSections(), true)) {
            $this->section = 'register';
        }

        $data = [
            'canManage' => $user->can('manage', [Risk::class, $this->event]),
            'canReport' => $user->can('reportIncident', [Risk::class, $this->event]),
            'full' => $full,
            'basic' => $basic,
            'closed' => in_array($this->event->status, [EventStatus::Archived, EventStatus::Cancelled], true),
            'summary' => $service->summary($this->event),
            'risks' => $service->register($this->event, [
                'status' => $this->filterStatus,
                'category' => $this->filterCategory,
                'cell' => $this->filterCell,
            ]),
            'categories' => RiskCategory::cases(),
            'responses' => RiskResponse::cases(),
            'statuses' => RiskStatus::cases(),
            'severities' => IncidentSeverity::cases(),
            'owners' => $this->event->members()->get()
                ->filter(fn ($u) => $u->pivot->role !== EventRole::Viewer->value)
                ->values(),
            'matrix' => [],
            'templates' => [],
            'incidents' => collect(),
            'allRisks' => collect(),
            'linkableTasks' => collect(),
            'existingTemplateKeys' => [],
        ];

        if ($full && $this->section === 'matrix') {
            $data['matrix'] = $service->matrix($this->event);
        }

        if ($full && $data['canManage'] && $this->section === 'templates') {
            $data['templates'] = $service->templates();
            $data['existingTemplateKeys'] = Risk::query()->forEvent($this->event)
                ->whereNotNull('template_key')->pluck('template_key')->all();
        }

        if ($full && $this->section === 'incidents') {
            $data['incidents'] = $service->incidents($this->event);
            $data['allRisks'] = Risk::query()->forEvent($this->event)->byScore()->get(['id', 'title']);
        }

        if ($data['canManage'] && $this->expandedRiskId !== null) {
            $data['linkableTasks'] = $this->event->tasks()->orderByDesc('id')->limit(100)->get(['id', 'title']);
        }

        return view('livewire.events.event-risk', $data);
    }

    // -------------------------------------------------------------- internals

    /**
     * @return list<string>
     */
    private function allowedSections(): array
    {
        $allowed = ['register'];

        if (app(RiskService::class)->hasFull(Auth::user())) {
            $allowed[] = 'matrix';
            $allowed[] = 'incidents';

            if (Auth::user()->can('manage', [Risk::class, $this->event])) {
                $allowed[] = 'templates';
            }
        }

        return $allowed;
    }

    private function findRisk(int $id): Risk
    {
        return Risk::query()->forEvent($this->event)->whereKey($id)->firstOrFail();
    }

    private function findIncident(int $id): Incident
    {
        return Incident::query()->forEvent($this->event)->whereKey($id)->firstOrFail();
    }

    /** تاریخ شمسی + ساعت «HH:MM» ← Carbon میلادی (null اگر نامعتبر). */
    private function parseOccurredAt(): ?\Illuminate\Support\Carbon
    {
        $date = Jalali::parse($this->iDate);
        $time = PhoneNumber::toLatinDigits(trim($this->iTime));

        if ($date === null || preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $time, $m) !== 1) {
            return null;
        }

        return $date->setTime((int) $m[1], (int) $m[2]);
    }

    private function clearMessages(): void
    {
        $this->error = null;
        $this->notice = null;
    }

    private function resetRiskForm(): void
    {
        $this->reset([
            'showRiskForm', 'editingRiskId', 'rTitle', 'rDescription', 'rControl', 'rOwnerId', 'rReview',
        ]);
        $this->rCategory = 'safety';
        $this->rLikelihood = '3';
        $this->rImpact = '3';
        $this->rResponse = 'reduce';
        $this->rStatus = 'open';
        $this->resetValidation();
    }

    private function resetIncidentForm(): void
    {
        $this->reset([
            'showIncidentForm', 'editingIncidentId', 'iTitle', 'iDate', 'iTime', 'iRiskId', 'iDescription',
        ]);
        $this->iSeverity = 'medium';
        $this->resetValidation();
    }
}
