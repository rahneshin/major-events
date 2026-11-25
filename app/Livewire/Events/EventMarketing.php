<?php

declare(strict_types=1);

namespace App\Livewire\Events;

use App\Enums\CampaignChannel;
use App\Enums\CampaignStatus;
use App\Enums\EventRole;
use App\Enums\EventStatus;
use App\Enums\KpiMetric;
use App\Enums\TaskPhase;
use App\Models\CampaignItem;
use App\Models\Event;
use App\Services\MarketingService;
use App\Support\Jalali;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Component;

/**
 * تب «بازاریابی» در هاب رویداد (Step 11): برنامه‌ی بازاریابی + تقویم محتوا (قبل/حین/بعد) + KPI.
 *
 * دسترسی (CampaignItemPolicy): view = هر کسی که رویداد را می‌بیند؛ manage = مدیر رویداد/سازمان.
 * هر اکشن جداگانه authorize می‌شود و هر شناسه فقط داخل همین رویداد پیدا می‌شود.
 * طرح حساب: فقط حساب سازمانی (`sponsorship`).
 */
class EventMarketing extends Component
{
    public Event $event;

    public string $section = 'calendar';

    public string $filterPhase = '';

    public string $filterStatus = 'all';

    // ---- برنامه
    public string $mSegments = '';

    public string $mPositioning = '';

    public string $mMessage = '';

    /** @var list<string> */
    public array $mChannels = [];

    // ---- فرم آیتم تقویم
    public bool $showItemForm = false;

    public ?int $editingItemId = null;

    public string $iTitle = '';

    public string $iPhase = 'pre';

    public string $iChannel = 'social';

    public string $iDate = '';

    public string $iTime = '';

    public string $iOwnerId = '';

    public string $iMetric = '';

    public string $iTarget = '';

    public string $iNotes = '';

    // ---- ثبت KPI
    public ?int $recordingKpiId = null;

    public string $kpiActual = '';

    public ?string $error = null;

    public ?string $notice = null;

    public function mount(Event $event): void
    {
        $this->authorize('view', [CampaignItem::class, $event]);
        $this->event = $event;
        $this->fillPlan();
    }

    public function setSection(string $section): void
    {
        $this->section = in_array($section, ['calendar', 'plan'], true) ? $section : 'calendar';
        $this->clearMessages();
    }

    // ------------------------------------------------------------------- plan

    public function savePlan(MarketingService $service): void
    {
        $this->authorize('manage', [CampaignItem::class, $this->event]);
        $this->clearMessages();

        $this->validate([
            'mSegments' => ['nullable', 'string', 'max:2000'],
            'mPositioning' => ['nullable', 'string', 'max:'.MarketingService::MAX_TEXT_LENGTH],
            'mMessage' => ['nullable', 'string', 'max:'.MarketingService::MAX_TEXT_LENGTH],
            'mChannels' => ['array'],
            'mChannels.*' => ['in:'.implode(',', CampaignChannel::values())],
        ], [], [
            'mSegments' => __('marketing.fields.target_segments'),
            'mPositioning' => __('marketing.fields.positioning'),
            'mMessage' => __('marketing.fields.message'),
            'mChannels' => __('marketing.fields.channels'),
        ]);

        try {
            $service->savePlan($this->event, Auth::user(), [
                'target_segments' => $this->mSegments,
                'positioning' => $this->mPositioning,
                'message' => $this->mMessage,
                'channels' => array_values($this->mChannels),
            ]);

            $this->fillPlan();
            $this->notice = __('marketing.plan_saved');
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    // ------------------------------------------------------------------ items

    public function openItemForm(string $phase = 'pre'): void
    {
        $this->authorize('manage', [CampaignItem::class, $this->event]);
        $this->resetItemForm();
        $this->section = 'calendar';
        $this->iPhase = TaskPhase::tryFrom($phase) !== null ? $phase : 'pre';
        $this->showItemForm = true;
    }

    public function cancelItemForm(): void
    {
        $this->resetItemForm();
    }

    public function editItem(int $id): void
    {
        $this->authorize('manage', [CampaignItem::class, $this->event]);

        $item = $this->findItem($id);

        $this->clearMessages();
        $this->editingItemId = $item->id;
        $this->iTitle = $item->title;
        $this->iPhase = $item->phase->value;
        $this->iChannel = $item->channel->value;
        $this->iDate = $item->publish_at?->toDateString() ?? '';
        $this->iTime = $item->publish_at !== null ? fa_num($item->publish_at->format('H:i')) : '';
        $this->iOwnerId = (string) ($item->owner_id ?? '');
        $this->iMetric = $item->kpi_metric?->value ?? '';
        $this->iTarget = $item->kpi_target !== null ? (string) $item->kpi_target : '';
        $this->iNotes = (string) $item->notes;
        $this->section = 'calendar';
        $this->showItemForm = true;
    }

    public function saveItem(MarketingService $service): void
    {
        $this->authorize('manage', [CampaignItem::class, $this->event]);
        $this->clearMessages();

        $this->validate([
            'iTitle' => ['required', 'string', 'max:'.MarketingService::MAX_TITLE_LENGTH],
            'iPhase' => ['required', 'in:'.implode(',', array_column(TaskPhase::cases(), 'value'))],
            'iChannel' => ['required', 'in:'.implode(',', CampaignChannel::values())],
            'iDate' => ['nullable', 'string', 'max:20'],
            'iTime' => ['nullable', 'string', 'max:5'],
            'iOwnerId' => ['nullable', 'string'],
            'iMetric' => ['nullable', 'in:'.implode(',', array_column(KpiMetric::cases(), 'value'))],
            'iTarget' => ['nullable', 'string', 'max:15'],
            'iNotes' => ['nullable', 'string', 'max:'.MarketingService::MAX_TEXT_LENGTH],
        ], [], [
            'iTitle' => __('marketing.fields.title'),
            'iPhase' => __('marketing.fields.phase'),
            'iChannel' => __('marketing.fields.channel'),
            'iDate' => __('marketing.fields.publish_date'),
            'iTime' => __('marketing.fields.publish_time'),
            'iOwnerId' => __('marketing.fields.owner'),
            'iMetric' => __('marketing.fields.kpi_metric'),
            'iTarget' => __('marketing.fields.kpi_target'),
            'iNotes' => __('marketing.fields.notes'),
        ]);

        $publishAt = $this->parsePublishAt();

        if ($publishAt === false) {
            $this->addError('iDate', __('marketing.date_invalid'));

            return;
        }

        $data = [
            'title' => $this->iTitle,
            'phase' => $this->iPhase,
            'channel' => $this->iChannel,
            'publish_at' => $publishAt,
            'owner_id' => $this->iOwnerId !== '' ? $this->iOwnerId : null,
            'kpi_metric' => $this->iMetric !== '' ? $this->iMetric : null,
            'kpi_target' => PhoneNumber::toLatinDigits(trim($this->iTarget)),
            'notes' => $this->iNotes,
        ];

        try {
            if ($this->editingItemId !== null) {
                $service->updateItem($this->findItem($this->editingItemId), Auth::user(), $data);
                $this->notice = __('marketing.item_updated');
            } else {
                $service->createItem($this->event, Auth::user(), $data);
                $this->notice = __('marketing.item_created');
            }

            $this->resetItemForm();
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function changeItemStatus(int $id, string $status, MarketingService $service): void
    {
        $this->authorize('manage', [CampaignItem::class, $this->event]);
        $this->clearMessages();

        $case = CampaignStatus::tryFrom($status);
        if ($case === null) {
            $this->error = __('marketing.invalid_value');

            return;
        }

        try {
            $service->setItemStatus($this->findItem($id), Auth::user(), $case);
            $this->notice = __('marketing.status_changed');
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function deleteItem(int $id, MarketingService $service): void
    {
        $this->authorize('manage', [CampaignItem::class, $this->event]);
        $this->clearMessages();

        try {
            $service->deleteItem($this->findItem($id), Auth::user());
            $this->notice = __('marketing.item_deleted');
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    // ------------------------------------------------------------------- KPI

    public function startKpi(int $id): void
    {
        $this->authorize('manage', [CampaignItem::class, $this->event]);

        $item = $this->findItem($id);

        $this->clearMessages();
        $this->recordingKpiId = $item->id;
        $this->kpiActual = $item->kpi_actual !== null ? (string) $item->kpi_actual : '';
    }

    public function cancelKpi(): void
    {
        $this->recordingKpiId = null;
        $this->kpiActual = '';
    }

    public function saveKpi(MarketingService $service): void
    {
        $this->authorize('manage', [CampaignItem::class, $this->event]);
        $this->clearMessages();

        if ($this->recordingKpiId === null) {
            return;
        }

        try {
            $service->recordKpi(
                $this->findItem($this->recordingKpiId),
                Auth::user(),
                PhoneNumber::toLatinDigits(trim($this->kpiActual)),
            );
            $this->cancelKpi();
            $this->notice = __('marketing.kpi_saved');
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    // ----------------------------------------------------------------- render

    public function render(MarketingService $service)
    {
        $user = Auth::user();
        $feature = $service->hasFeature($user);
        $canManage = $user->can('manage', [CampaignItem::class, $this->event]);
        $closed = in_array($this->event->status, [EventStatus::Archived, EventStatus::Cancelled], true);

        $data = [
            'feature' => $feature,
            'canManage' => $canManage,
            'canEdit' => $canManage && $feature && ! $closed,
            'closed' => $closed,
            'summary' => null,
            'items' => collect(),
            'plan' => null,
            'phases' => TaskPhase::cases(),
            'channels' => CampaignChannel::cases(),
            'statuses' => CampaignStatus::cases(),
            'metrics' => KpiMetric::cases(),
            'owners' => collect(),
        ];

        if (! $feature) {
            return view('livewire.events.event-marketing', $data);
        }

        $data['summary'] = $service->summary($this->event);
        $data['plan'] = $service->plan($this->event);
        $data['items'] = $service->calendar($this->event, [
            'phase' => $this->filterPhase,
            'status' => $this->filterStatus,
        ]);
        $data['owners'] = $this->event->members()->get()
            ->filter(fn ($u) => $u->pivot->role !== EventRole::Viewer->value)
            ->values();

        return view('livewire.events.event-marketing', $data);
    }

    // -------------------------------------------------------------- internals

    private function findItem(int $id): CampaignItem
    {
        return CampaignItem::query()->forEvent($this->event)->whereKey($id)->firstOrFail();
    }

    private function fillPlan(): void
    {
        $plan = app(MarketingService::class)->plan($this->event);

        $this->mSegments = implode("\n", $plan?->target_segments ?? []);
        $this->mPositioning = (string) $plan?->positioning;
        $this->mMessage = (string) $plan?->message;
        $this->mChannels = array_values($plan?->channels ?? []);
    }

    /**
     * تاریخ (شمسی/ISO) + ساعت «HH:MM» اختیاری ← Carbon؛ بدون تاریخ ← null؛ نامعتبر ← false.
     */
    private function parsePublishAt(): \Illuminate\Support\Carbon|false|null
    {
        if (trim($this->iDate) === '') {
            return null;
        }

        $date = Jalali::parse($this->iDate);

        if ($date === null) {
            return false;
        }

        $time = PhoneNumber::toLatinDigits(trim($this->iTime));

        if ($time === '') {
            return $date->startOfDay();
        }

        if (preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $time, $m) !== 1) {
            return false;
        }

        return $date->setTime((int) $m[1], (int) $m[2]);
    }

    private function clearMessages(): void
    {
        $this->error = null;
        $this->notice = null;
    }

    private function resetItemForm(): void
    {
        $this->reset([
            'showItemForm', 'editingItemId', 'iTitle', 'iDate', 'iTime', 'iOwnerId', 'iMetric', 'iTarget', 'iNotes',
        ]);
        $this->iPhase = 'pre';
        $this->iChannel = 'social';
        $this->resetValidation();
    }
}
