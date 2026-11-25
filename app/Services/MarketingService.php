<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CampaignChannel;
use App\Enums\CampaignStatus;
use App\Enums\EventRole;
use App\Enums\EventStatus;
use App\Enums\KpiMetric;
use App\Enums\TaskPhase;
use App\Models\CampaignItem;
use App\Models\Event;
use App\Models\MarketingPlan;
use App\Models\User;
use App\Support\LogsActivity;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Throwable;

/**
 * Step 11 — بازاریابی: برنامه (مخاطب، جایگاه‌یابی، پیام، کانال) و تقویم محتوا (قبل/حین/بعد) با KPI.
 *
 * قواعد:
 *  - ماژول فقط برای حساب سازمانی است (ویژگی `sponsorship`، همان کلید اسپانسرینگ).
 *  - هر رویداد حداکثر یک برنامه‌ی بازاریابی دارد (ثبت دوباره = به‌روزرسانی).
 *  - مسئول آیتم تقویم باید عضو غیر-بیننده‌ی همین رویداد باشد.
 *  - KPI یکی از آگاهی/دسترسی/تعامل است؛ هدف و مقدار واقعی بدون انتخاب شاخص ثبت نمی‌شود.
 *  - آیتم لغوشده در جمع KPI حساب نمی‌شود.
 *  - مجوز در CampaignItemPolicy است؛ این سرویس فقط قواعد کسب‌وکار و ویژگی طرح را اعمال می‌کند.
 */
class MarketingService
{
    use LogsActivity;

    public const MAX_TITLE_LENGTH = 160;

    public const MAX_TEXT_LENGTH = 2000;

    public function __construct(
        private EntitlementService $entitlement,
    ) {}

    public function hasFeature(User $actor): bool
    {
        return $this->entitlement->can($actor, SponsorshipService::FEATURE);
    }

    // -------------------------------------------------------------------- plan

    public function plan(Event $event): ?MarketingPlan
    {
        return MarketingPlan::query()->where('event_id', $event->id)->first();
    }

    /**
     * @param  array<string, mixed>  $data  target_segments (لیست یا متن چندخطی)، positioning, message, channels
     *
     * @throws InvalidArgumentException
     */
    public function savePlan(Event $event, User $actor, array $data): MarketingPlan
    {
        $this->assertWritable($event, $actor);

        $payload = [];

        if (array_key_exists('target_segments', $data)) {
            $payload['target_segments'] = $this->segments($data['target_segments']);
        }
        if (array_key_exists('positioning', $data)) {
            $payload['positioning'] = $this->cleanText($data['positioning'], self::MAX_TEXT_LENGTH);
        }
        if (array_key_exists('message', $data)) {
            $payload['message'] = $this->cleanText($data['message'], self::MAX_TEXT_LENGTH);
        }
        if (array_key_exists('channels', $data)) {
            $payload['channels'] = $this->channels($data['channels']);
        }

        $plan = MarketingPlan::query()->updateOrCreate(
            ['event_id' => $event->id],
            $payload + ['updated_by' => $actor->id],
        );

        self::logActivity('marketing.plan_saved', $plan, $event->id, [
            'fields' => array_keys($payload),
        ]);

        return $plan->refresh();
    }

    // ---------------------------------------------------------------- campaign

    /**
     * @param  array<string, mixed>  $data  phase, channel, title, publish_at, owner_id, kpi_metric,
     *                                      kpi_target, notes
     *
     * @throws InvalidArgumentException
     */
    public function createItem(Event $event, User $actor, array $data): CampaignItem
    {
        $this->assertWritable($event, $actor);

        $payload = $this->itemPayload($event, $data, creating: true);

        $item = CampaignItem::query()->create($payload + [
            'event_id' => $event->id,
            'status' => CampaignStatus::Planned,
            'created_by' => $actor->id,
        ]);

        self::logActivity('campaign.created', $item, $event->id, ['title' => $item->title]);

        return $item->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidArgumentException
     */
    public function updateItem(CampaignItem $item, User $actor, array $data): CampaignItem
    {
        $this->assertWritable($item->event, $actor);

        $payload = $this->itemPayload($item->event, $data, creating: false, current: $item);

        if ($payload === []) {
            return $item;
        }

        $item->fill($payload)->save();

        $changes = array_values(array_diff(array_keys($item->getChanges()), ['updated_at']));
        if ($changes !== []) {
            self::logActivity('campaign.updated', $item, $item->event_id, ['changes' => $changes]);
        }

        return $item->refresh();
    }

    public function setItemStatus(CampaignItem $item, User $actor, CampaignStatus $status): CampaignItem
    {
        $this->assertWritable($item->event, $actor);

        if ($item->status === $status) {
            return $item;
        }

        $from = $item->status;
        $item->update(['status' => $status]);

        self::logActivity('campaign.status_changed', $item, $item->event_id, [
            'from' => $from?->value,
            'to' => $status->value,
        ]);

        return $item->refresh();
    }

    /**
     * ثبت مقدار واقعی KPI (فقط وقتی شاخص انتخاب شده باشد).
     *
     * @throws InvalidArgumentException
     */
    public function recordKpi(CampaignItem $item, User $actor, mixed $actual): CampaignItem
    {
        $this->assertWritable($item->event, $actor);

        if ($item->kpi_metric === null) {
            throw new InvalidArgumentException(__('marketing.kpi_metric_required'));
        }

        $value = $this->kpiNumber($actual);

        if ($value === null) {
            throw new InvalidArgumentException(__('marketing.kpi_number_invalid'));
        }

        $item->update(['kpi_actual' => $value]);

        self::logActivity('campaign.kpi_recorded', $item, $item->event_id, ['actual' => $value]);

        return $item->refresh();
    }

    public function deleteItem(CampaignItem $item, User $actor): void
    {
        $this->assertWritable($item->event, $actor);

        self::logActivity('campaign.deleted', $item, $item->event_id, ['title' => $item->title]);

        $item->delete();
    }

    /**
     * تقویم محتوا: بر اساس تاریخ انتشار (بدون تاریخ آخر).
     *
     * @param  array{phase?: ?string, status?: ?string, channel?: ?string}  $filters
     *                                                                                status: 'open' | 'all' (default) | a CampaignStatus value
     * @return Collection<int, CampaignItem>
     */
    public function calendar(Event $event, array $filters = []): Collection
    {
        $query = CampaignItem::query()->forEvent($event)->with('owner')->calendarOrder();

        if (! empty($filters['phase']) && TaskPhase::tryFrom((string) $filters['phase']) !== null) {
            $query->where('phase', $filters['phase']);
        }

        if (! empty($filters['channel']) && CampaignChannel::tryFrom((string) $filters['channel']) !== null) {
            $query->where('channel', $filters['channel']);
        }

        $status = $filters['status'] ?? 'all';
        if ($status === 'open') {
            $query->whereIn('status', [CampaignStatus::Planned->value, CampaignStatus::InProgress->value]);
        } elseif ($status !== 'all' && $status !== null && $status !== '' && CampaignStatus::tryFrom((string) $status) !== null) {
            $query->where('status', $status);
        }

        return $query->get();
    }

    /**
     * @return array{
     *     total: int,
     *     by_phase: array<string, int>,
     *     by_status: array<string, int>,
     *     late: int,
     *     kpi: array<string, array{items: int, target: int, actual: int, percent: ?int}>
     * }
     */
    public function summary(Event $event): array
    {
        $items = CampaignItem::query()->forEvent($event)->get();

        $byPhase = [];
        foreach (TaskPhase::cases() as $phase) {
            $byPhase[$phase->value] = 0;
        }

        $byStatus = [];
        foreach (CampaignStatus::cases() as $status) {
            $byStatus[$status->value] = 0;
        }

        $kpi = [];
        foreach (KpiMetric::cases() as $metric) {
            $kpi[$metric->value] = ['items' => 0, 'target' => 0, 'actual' => 0, 'percent' => null];
        }

        foreach ($items as $item) {
            $byPhase[$item->phase->value]++;
            $byStatus[$item->status->value]++;

            if ($item->status === CampaignStatus::Cancelled || $item->kpi_metric === null) {
                continue;
            }

            $row = &$kpi[$item->kpi_metric->value];
            $row['items']++;
            $row['target'] += (int) $item->kpi_target;
            $row['actual'] += (int) $item->kpi_actual;
            unset($row);
        }

        foreach ($kpi as $key => $row) {
            $kpi[$key]['percent'] = $row['target'] > 0 ? (int) min(999, intdiv($row['actual'] * 100, $row['target'])) : null;
        }

        return [
            'total' => $items->count(),
            'by_phase' => $byPhase,
            'by_status' => $byStatus,
            'late' => $items->filter(fn (CampaignItem $i) => $i->isLate())->count(),
            'kpi' => $kpi,
        ];
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    private function itemPayload(Event $event, array $data, bool $creating, ?CampaignItem $current = null): array
    {
        $payload = [];

        if ($creating || array_key_exists('title', $data)) {
            $title = trim((string) ($data['title'] ?? ''));

            if ($title === '') {
                throw new InvalidArgumentException(__('marketing.title_required'));
            }
            if (mb_strlen($title) > self::MAX_TITLE_LENGTH) {
                throw new InvalidArgumentException(__('marketing.title_too_long', ['max' => fa_num(self::MAX_TITLE_LENGTH)]));
            }

            $payload['title'] = $title;
        }

        if ($creating || array_key_exists('phase', $data)) {
            $payload['phase'] = $this->enumCase(TaskPhase::class, $data['phase'] ?? null)
                ?? throw new InvalidArgumentException(__('marketing.invalid_value'));
        }

        if ($creating || array_key_exists('channel', $data)) {
            $payload['channel'] = $this->enumCase(CampaignChannel::class, $data['channel'] ?? null)
                ?? throw new InvalidArgumentException(__('marketing.invalid_value'));
        }

        if (array_key_exists('publish_at', $data)) {
            $payload['publish_at'] = $this->parseDateTime($data['publish_at']);
        }

        if (array_key_exists('owner_id', $data)) {
            $payload['owner_id'] = $this->resolveOwnerId($event, $data['owner_id']);
        }

        if (array_key_exists('notes', $data)) {
            $payload['notes'] = $this->cleanText($data['notes'], self::MAX_TEXT_LENGTH);
        }

        // ---- KPI: شاخص + هدف (مقدار واقعی با recordKpi ثبت می‌شود)
        if (array_key_exists('kpi_metric', $data) || array_key_exists('kpi_target', $data)) {
            $metric = array_key_exists('kpi_metric', $data)
                ? $this->enumCase(KpiMetric::class, $data['kpi_metric'])
                : $current?->kpi_metric;

            if ($metric === null) {
                // بدون شاخص، هدف بی‌معنی است؛ شاخص برداشته شد ← هدف و مقدار واقعی هم پاک می‌شوند
                if (array_key_exists('kpi_target', $data) && $this->kpiNumber($data['kpi_target']) !== null) {
                    throw new InvalidArgumentException(__('marketing.kpi_metric_required'));
                }

                $payload['kpi_metric'] = null;
                $payload['kpi_target'] = null;
                $payload['kpi_actual'] = null;
            } else {
                $target = array_key_exists('kpi_target', $data)
                    ? $this->kpiNumber($data['kpi_target'])
                    : $current?->kpi_target;

                if ($target !== null && $target < 1) {
                    throw new InvalidArgumentException(__('marketing.kpi_target_invalid'));
                }

                $payload['kpi_metric'] = $metric;
                $payload['kpi_target'] = $target;
            }
        }

        return $payload;
    }

    /**
     * عدد صحیح نامنفی؛ خالی ← null؛ مقدار نامعتبر ← استثنا.
     */
    private function kpiNumber(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value) && preg_match('/^\d{1,15}$/', trim($value)) === 1) {
            $value = (int) trim($value);
        }

        if (! is_int($value) || $value < 0) {
            throw new InvalidArgumentException(__('marketing.kpi_number_invalid'));
        }

        return $value;
    }

    /**
     * بخش‌های مخاطب: لیست یا متن چندخطی (جداکننده: خط جدید، «،» یا «,»)؛ تکراری‌ها حذف می‌شوند.
     *
     * @return list<string>
     *
     * @throws InvalidArgumentException
     */
    private function segments(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/[\r\n,،]+/u', $value) ?: [];
        }

        if (! is_array($value)) {
            throw new InvalidArgumentException(__('marketing.invalid_value'));
        }

        $max = (int) config('sponsorship.max_segments', 12);
        $maxLength = (int) config('sponsorship.max_segment_length', 80);
        $clean = [];

        foreach ($value as $segment) {
            $segment = trim((string) $segment);

            if ($segment === '' || in_array($segment, $clean, true)) {
                continue;
            }

            if (mb_strlen($segment) > $maxLength) {
                throw new InvalidArgumentException(__('marketing.segment_too_long', ['max' => fa_num($maxLength)]));
            }

            $clean[] = $segment;
        }

        if (count($clean) > $max) {
            throw new InvalidArgumentException(__('marketing.segments_too_many', ['max' => fa_num($max)]));
        }

        return $clean;
    }

    /**
     * @return list<string>
     *
     * @throws InvalidArgumentException
     */
    private function channels(mixed $value): array
    {
        if (! is_array($value)) {
            throw new InvalidArgumentException(__('marketing.invalid_value'));
        }

        $clean = [];

        foreach ($value as $channel) {
            $case = $channel instanceof CampaignChannel ? $channel : CampaignChannel::tryFrom((string) $channel);

            if ($case === null) {
                throw new InvalidArgumentException(__('marketing.invalid_value'));
            }

            if (! in_array($case->value, $clean, true)) {
                $clean[] = $case->value;
            }
        }

        return $clean;
    }

    private function resolveOwnerId(Event $event, mixed $ownerId): ?int
    {
        if ($ownerId === null || $ownerId === '') {
            return null;
        }

        $user = is_numeric($ownerId) ? User::query()->find((int) $ownerId) : null;
        $role = $user !== null ? $event->roleOf($user) : null;

        if ($user === null || $role === null || $role === EventRole::Viewer) {
            throw new InvalidArgumentException(__('marketing.owner_invalid'));
        }

        return $user->id;
    }

    private function cleanText(mixed $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }
        if (mb_strlen($text) > $max) {
            throw new InvalidArgumentException(__('marketing.text_too_long', ['max' => fa_num($max)]));
        }

        return $text;
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
            ?? throw new InvalidArgumentException(__('marketing.invalid_value'));
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
            throw new InvalidArgumentException(__('marketing.date_invalid'));
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    private function assertWritable(Event $event, User $actor): void
    {
        if (! $this->hasFeature($actor)) {
            throw new InvalidArgumentException(__('marketing.feature_unavailable'));
        }

        if (in_array($event->status, [EventStatus::Archived, EventStatus::Cancelled], true)) {
            throw new InvalidArgumentException(__('marketing.event_closed'));
        }
    }
}
