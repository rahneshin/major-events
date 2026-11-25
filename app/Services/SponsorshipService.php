<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AssetType;
use App\Enums\DeliverableStatus;
use App\Enums\EventStatus;
use App\Enums\SponsorCategory;
use App\Enums\SponsorStatus;
use App\Enums\TaskModule;
use App\Enums\TaskPhase;
use App\Enums\TaskPriority;
use App\Exceptions\LimitExceededException;
use App\Models\Event;
use App\Models\Sponsor;
use App\Models\SponsorDeliverable;
use App\Models\SponsorshipAsset;
use App\Models\SponsorshipPackage;
use App\Models\Task;
use App\Models\User;
use App\Support\LogsActivity;
use App\Support\PhoneNumber;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

/**
 * Step 11 — اسپانسرینگ.
 *
 * قواعد:
 *  - ماژول فقط برای حساب سازمانی است (ویژگی `sponsorship`)؛ بدون آن همه‌ی عملیات رد می‌شود.
 *  - وضعیت اسپانسر: prospect → negotiating → signed → delivered، و lost. «delivered» فقط وقتی
 *    که هیچ تعهد بازی نمانده باشد؛ «lost» تعهدهای باز را لغو می‌کند.
 *  - موجودی دارایی: مجموع تعداد تعهدهای غیرلغوشده نباید از `inventory` بیشتر شود.
 *  - تعهد معوق = موعد گذشته و هنوز pending/in_progress.
 *  - هر چیز (دارایی، بسته، تعهد، وظیفه) فقط داخل همان رویداد پیدا می‌شود.
 *  - مبلغ‌ها عدد صحیح ریال‌اند. مدرک تحویل روی دیسک خصوصی `local` می‌ماند.
 *  - مجوز در SponsorPolicy است؛ این سرویس فقط قواعد کسب‌وکار و ویژگی طرح را اعمال می‌کند.
 */
class SponsorshipService
{
    use LogsActivity;

    public const FEATURE = 'sponsorship';

    public const MAX_NAME_LENGTH = 160;

    public const MAX_COMMITMENT_LENGTH = 255;

    public const MAX_TEXT_LENGTH = 2000;

    public function __construct(
        private EntitlementService $entitlement,
        private TaskService $tasks,
    ) {}

    // ------------------------------------------------------------ entitlement

    public function hasFeature(User $actor): bool
    {
        return $this->entitlement->can($actor, self::FEATURE);
    }

    // ---------------------------------------------------------------- sponsors

    /**
     * @param  array<string, mixed>  $data  name, category, contact_name, contact_phone, cash_value,
     *                                      in_kind_value, notes, package_id
     *
     * @throws InvalidArgumentException
     */
    public function createSponsor(Event $event, User $actor, array $data): Sponsor
    {
        $this->assertWritable($event, $actor);

        $payload = $this->sponsorPayload($event, $data, creating: true);

        $sponsor = Sponsor::query()->create($payload + [
            'event_id' => $event->id,
            'status' => SponsorStatus::Prospect,
            'created_by' => $actor->id,
        ]);

        self::logActivity('sponsor.created', $sponsor, $event->id, ['name' => $sponsor->name]);

        return $sponsor->refresh();
    }

    /**
     * Descriptive fields only. Status changes go through transitionSponsor().
     *
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidArgumentException
     */
    public function updateSponsor(Sponsor $sponsor, User $actor, array $data): Sponsor
    {
        $this->assertWritable($sponsor->event, $actor);

        $payload = $this->sponsorPayload($sponsor->event, $data, creating: false);

        if ($payload === []) {
            return $sponsor;
        }

        $sponsor->fill($payload)->save();

        $changes = array_values(array_diff(array_keys($sponsor->getChanges()), ['updated_at']));
        if ($changes !== []) {
            self::logActivity('sponsor.updated', $sponsor, $sponsor->event_id, ['changes' => $changes]);
        }

        return $sponsor->refresh();
    }

    /**
     * @throws InvalidArgumentException
     */
    public function transitionSponsor(Sponsor $sponsor, User $actor, SponsorStatus $to): Sponsor
    {
        $this->assertWritable($sponsor->event, $actor);

        $from = $sponsor->status;

        if ($from === $to) {
            return $sponsor;
        }

        if (! $from->canTransitionTo($to)) {
            throw new InvalidArgumentException(__('sponsorship.transition_invalid', [
                'from' => $from->label(),
                'to' => $to->label(),
            ]));
        }

        if ($to === SponsorStatus::Delivered && $sponsor->hasOpenDeliverables()) {
            throw new InvalidArgumentException(__('sponsorship.delivered_needs_closed_deliverables'));
        }

        DB::transaction(function () use ($sponsor, $from, $to) {
            $cancelled = 0;

            // اسپانسر از دست رفته تعهد باز ندارد؛ موجودی دارایی‌ها آزاد می‌شود
            if ($to === SponsorStatus::Lost) {
                $cancelled = $sponsor->deliverables()
                    ->whereIn('status', DeliverableStatus::openValues())
                    ->update(['status' => DeliverableStatus::Cancelled->value]);
            }

            $sponsor->update(['status' => $to]);

            self::logActivity('sponsor.status_changed', $sponsor, $sponsor->event_id, [
                'from' => $from->value,
                'to' => $to->value,
                'cancelled_deliverables' => $cancelled,
            ]);
        });

        return $sponsor->refresh();
    }

    /**
     * حذف نرم اسپانسر و تعهدهایش (مدرک‌های روی دیسک برای ممیزی می‌مانند).
     */
    public function deleteSponsor(Sponsor $sponsor, User $actor): void
    {
        $this->assertWritable($sponsor->event, $actor);

        DB::transaction(function () use ($sponsor) {
            self::logActivity('sponsor.deleted', $sponsor, $sponsor->event_id, ['name' => $sponsor->name]);

            $sponsor->deliverables()->get()->each(fn (SponsorDeliverable $d) => $d->delete());
            $sponsor->delete();
        });
    }

    /**
     * @param  array{status?: ?string, category?: ?string}  $filters
     * @return Collection<int, Sponsor>
     */
    public function sponsors(Event $event, array $filters = []): Collection
    {
        $query = Sponsor::query()->forEvent($event)->with('package')->withCount([
            'deliverables as open_deliverables_count' => fn ($q) => $q->whereIn('status', DeliverableStatus::openValues()),
            'deliverables as total_deliverables_count' => fn ($q) => $q->where('status', '!=', DeliverableStatus::Cancelled->value),
        ])->ordered();

        if (! empty($filters['status']) && SponsorStatus::tryFrom((string) $filters['status']) !== null) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['category']) && SponsorCategory::tryFrom((string) $filters['category']) !== null) {
            $query->where('category', $filters['category']);
        }

        return $query->get();
    }

    // ------------------------------------------------------------------ assets

    /**
     * @param  array<string, mixed>  $data  name, type, inventory, price, description
     *
     * @throws InvalidArgumentException
     */
    public function createAsset(Event $event, User $actor, array $data): SponsorshipAsset
    {
        $this->assertWritable($event, $actor);

        $payload = $this->assetPayload($data, creating: true);

        $asset = SponsorshipAsset::query()->create($payload + [
            'event_id' => $event->id,
            'created_by' => $actor->id,
        ]);

        self::logActivity('sponsorship.asset_created', $asset, $event->id, ['name' => $asset->name]);

        return $asset->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidArgumentException
     */
    public function updateAsset(SponsorshipAsset $asset, User $actor, array $data): SponsorshipAsset
    {
        $this->assertWritable($asset->event, $actor);

        $payload = $this->assetPayload($data, creating: false);

        if (isset($payload['inventory']) && $payload['inventory'] < $asset->allocatedQuantity()) {
            throw new InvalidArgumentException(__('sponsorship.inventory_below_allocated', [
                'allocated' => fa_num($asset->allocatedQuantity()),
            ]));
        }

        if ($payload === []) {
            return $asset;
        }

        $asset->fill($payload)->save();

        $changes = array_values(array_diff(array_keys($asset->getChanges()), ['updated_at']));
        if ($changes !== []) {
            self::logActivity('sponsorship.asset_updated', $asset, $asset->event_id, ['changes' => $changes]);
        }

        return $asset->refresh();
    }

    /**
     * دارایی‌ای که به تعهد فعال اختصاص داده شده حذف نمی‌شود.
     *
     * @throws InvalidArgumentException
     */
    public function deleteAsset(SponsorshipAsset $asset, User $actor): void
    {
        $this->assertWritable($asset->event, $actor);

        if ($asset->deliverables()->where('status', '!=', DeliverableStatus::Cancelled->value)->exists()) {
            throw new InvalidArgumentException(__('sponsorship.asset_in_use'));
        }

        DB::transaction(function () use ($asset) {
            self::logActivity('sponsorship.asset_deleted', $asset, $asset->event_id, ['name' => $asset->name]);

            $asset->packages()->detach();
            $asset->delete();
        });
    }

    /**
     * @return Collection<int, SponsorshipAsset>
     */
    public function assets(Event $event): Collection
    {
        $assets = SponsorshipAsset::query()->forEvent($event)->orderBy('type')->orderBy('id')->get();

        // یک query برای کل تخصیص‌ها (به‌جای N+1)
        $allocated = SponsorDeliverable::query()
            ->forEvent($event)
            ->whereNotNull('asset_id')
            ->where('status', '!=', DeliverableStatus::Cancelled->value)
            ->selectRaw('asset_id, SUM(quantity) as qty')
            ->groupBy('asset_id')
            ->pluck('qty', 'asset_id');

        return $assets->each(function (SponsorshipAsset $asset) use ($allocated) {
            $asset->setAttribute('allocated', (int) ($allocated[$asset->id] ?? 0));
            $asset->setAttribute('remaining', max(0, (int) $asset->inventory - (int) ($allocated[$asset->id] ?? 0)));
        });
    }

    // ---------------------------------------------------------------- packages

    /**
     * @param  array<string, mixed>  $data  name, description, price, assets: [asset_id => quantity]
     *
     * @throws InvalidArgumentException
     */
    public function createPackage(Event $event, User $actor, array $data): SponsorshipPackage
    {
        $this->assertWritable($event, $actor);

        $payload = $this->packagePayload($data, creating: true);
        $assets = $this->resolvePackageAssets($event, $data['assets'] ?? []);

        return DB::transaction(function () use ($event, $actor, $payload, $assets) {
            $package = SponsorshipPackage::query()->create($payload + [
                'event_id' => $event->id,
                'created_by' => $actor->id,
            ]);

            $package->assets()->sync($assets);

            self::logActivity('sponsorship.package_created', $package, $event->id, ['name' => $package->name]);

            return $package->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data  (کلید `assets` اگر باشد، ترکیب دارایی‌ها را جایگزین می‌کند)
     *
     * @throws InvalidArgumentException
     */
    public function updatePackage(SponsorshipPackage $package, User $actor, array $data): SponsorshipPackage
    {
        $event = $package->event;
        $this->assertWritable($event, $actor);

        $payload = $this->packagePayload($data, creating: false);
        $assets = array_key_exists('assets', $data) ? $this->resolvePackageAssets($event, $data['assets']) : null;

        DB::transaction(function () use ($package, $payload, $assets) {
            if ($payload !== []) {
                $package->fill($payload)->save();
            }

            if ($assets !== null) {
                $package->assets()->sync($assets);
            }

            self::logActivity('sponsorship.package_updated', $package, $package->event_id, [
                'changes' => array_values(array_diff(array_keys($package->getChanges()), ['updated_at'])),
            ]);
        });

        return $package->refresh();
    }

    /**
     * اسپانسرهایی که این بسته را داشتند بدون بسته می‌شوند (تعهدهایشان دست‌نخورده می‌ماند).
     */
    public function deletePackage(SponsorshipPackage $package, User $actor): void
    {
        $this->assertWritable($package->event, $actor);

        DB::transaction(function () use ($package) {
            self::logActivity('sponsorship.package_deleted', $package, $package->event_id, ['name' => $package->name]);

            Sponsor::query()->where('package_id', $package->id)->update(['package_id' => null]);
            $package->assets()->detach();
            $package->delete();
        });
    }

    /**
     * @return Collection<int, SponsorshipPackage>
     */
    public function packages(Event $event): Collection
    {
        return SponsorshipPackage::query()
            ->forEvent($event)
            ->with('assets')
            ->withCount('sponsors')
            ->orderBy('price')
            ->orderBy('id')
            ->get();
    }

    // ------------------------------------------------------------ deliverables

    /**
     * @param  array<string, mixed>  $data  commitment, asset_id, quantity, due_at
     *
     * @throws InvalidArgumentException
     */
    public function addDeliverable(Sponsor $sponsor, User $actor, array $data): SponsorDeliverable
    {
        $event = $sponsor->event;
        $this->assertWritable($event, $actor);

        if (! in_array($sponsor->status, [SponsorStatus::Prospect, SponsorStatus::Negotiating, SponsorStatus::Signed], true)) {
            throw new InvalidArgumentException(__('sponsorship.deliverable_sponsor_status'));
        }

        $asset = $this->resolveAsset($event, $data['asset_id'] ?? null);
        $quantity = $this->quantity($data['quantity'] ?? 1);
        $commitment = $this->cleanText($data['commitment'] ?? null, self::MAX_COMMITMENT_LENGTH);

        if ($commitment === null) {
            // بدون متن، نام دارایی خودش تعهد است
            $commitment = $asset?->name;
        }
        if ($commitment === null) {
            throw new InvalidArgumentException(__('sponsorship.commitment_required'));
        }

        $this->assertInventory($asset, $quantity, null);

        $deliverable = SponsorDeliverable::query()->create([
            'event_id' => $event->id,
            'sponsor_id' => $sponsor->id,
            'asset_id' => $asset?->id,
            'commitment' => $commitment,
            'quantity' => $quantity,
            'due_at' => $this->parseDate($data['due_at'] ?? null)?->toDateString(),
            'status' => DeliverableStatus::Pending,
            'created_by' => $actor->id,
        ]);

        self::logActivity('sponsorship.deliverable_created', $deliverable, $event->id, [
            'sponsor_id' => $sponsor->id,
            'asset_id' => $asset?->id,
        ]);

        return $deliverable->refresh();
    }

    /**
     * برای هر دارایی بسته‌ی اسپانسر یک تعهد می‌سازد (تعهدی که قبلاً برای همان دارایی دارد دوباره ساخته نمی‌شود).
     * اگر موجودی یکی از دارایی‌ها کافی نباشد، هیچ تعهدی ساخته نمی‌شود.
     *
     * @return Collection<int, SponsorDeliverable> تعهدهای تازه
     *
     * @throws InvalidArgumentException
     */
    public function generateDeliverablesFromPackage(Sponsor $sponsor, User $actor, mixed $dueAt = null): Collection
    {
        $this->assertWritable($sponsor->event, $actor);

        $package = $sponsor->package;
        if ($package === null) {
            throw new InvalidArgumentException(__('sponsorship.sponsor_has_no_package'));
        }

        $existing = $sponsor->deliverables()
            ->where('status', '!=', DeliverableStatus::Cancelled->value)
            ->whereNotNull('asset_id')
            ->pluck('asset_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return DB::transaction(function () use ($sponsor, $actor, $package, $existing, $dueAt) {
            $created = collect();

            foreach ($package->assets as $asset) {
                if (in_array((int) $asset->id, $existing, true)) {
                    continue;
                }

                $created->push($this->addDeliverable($sponsor, $actor, [
                    'asset_id' => $asset->id,
                    'quantity' => (int) $asset->pivot->quantity,
                    'due_at' => $dueAt,
                ]));
            }

            return $created;
        });
    }

    /**
     * @param  array<string, mixed>  $data  commitment, asset_id, quantity, due_at
     *
     * @throws InvalidArgumentException
     */
    public function updateDeliverable(SponsorDeliverable $deliverable, User $actor, array $data): SponsorDeliverable
    {
        $event = $deliverable->event;
        $this->assertWritable($event, $actor);

        $payload = [];

        $asset = $deliverable->asset;
        if (array_key_exists('asset_id', $data)) {
            $asset = $this->resolveAsset($event, $data['asset_id']);
            $payload['asset_id'] = $asset?->id;
        }

        $quantity = (int) $deliverable->quantity;
        if (array_key_exists('quantity', $data)) {
            $quantity = $this->quantity($data['quantity']);
            $payload['quantity'] = $quantity;
        }

        if (array_key_exists('commitment', $data)) {
            $commitment = $this->cleanText($data['commitment'], self::MAX_COMMITMENT_LENGTH);
            if ($commitment === null) {
                throw new InvalidArgumentException(__('sponsorship.commitment_required'));
            }
            $payload['commitment'] = $commitment;
        }

        if (array_key_exists('due_at', $data)) {
            $payload['due_at'] = $this->parseDate($data['due_at'])?->toDateString();
        }

        // موجودی فقط برای تعهدی که هنوز لغو نشده بررسی می‌شود
        if ($deliverable->status !== DeliverableStatus::Cancelled
            && (array_key_exists('asset_id', $data) || array_key_exists('quantity', $data))) {
            $this->assertInventory($asset, $quantity, $deliverable->id);
        }

        if ($payload === []) {
            return $deliverable;
        }

        $deliverable->fill($payload)->save();

        $changes = array_values(array_diff(array_keys($deliverable->getChanges()), ['updated_at']));
        if ($changes !== []) {
            self::logActivity('sponsorship.deliverable_updated', $deliverable, $deliverable->event_id, ['changes' => $changes]);
        }

        return $deliverable->refresh();
    }

    /**
     * pending / in_progress / delivered / cancelled. تحویل‌شده زمان تحویل را ثبت می‌کند؛
     * بازکردن تعهد لغوشده موجودی دارایی را دوباره می‌سنجد.
     *
     * @throws InvalidArgumentException
     */
    public function setDeliverableStatus(SponsorDeliverable $deliverable, User $actor, DeliverableStatus $to): SponsorDeliverable
    {
        $this->assertWritable($deliverable->event, $actor);

        $from = $deliverable->status;

        if ($from === $to) {
            return $deliverable;
        }

        if ($from === DeliverableStatus::Cancelled) {
            $this->assertInventory($deliverable->asset, (int) $deliverable->quantity, $deliverable->id);
        }

        // تعهد باز برای اسپانسرِ از‌دست‌رفته یا تحویل‌شده معنی ندارد (اول وضعیت اسپانسر را برگردانید)
        if ($to->isOpen() && in_array($deliverable->sponsor?->status, [SponsorStatus::Lost, SponsorStatus::Delivered], true)) {
            throw new InvalidArgumentException(__('sponsorship.deliverable_sponsor_status'));
        }

        $deliverable->update([
            'status' => $to,
            'delivered_at' => $to === DeliverableStatus::Delivered ? now() : null,
        ]);

        self::logActivity('sponsorship.deliverable_status_changed', $deliverable, $deliverable->event_id, [
            'from' => $from->value,
            'to' => $to->value,
        ]);

        return $deliverable->refresh();
    }

    public function deleteDeliverable(SponsorDeliverable $deliverable, User $actor): void
    {
        $this->assertWritable($deliverable->event, $actor);

        self::logActivity('sponsorship.deliverable_deleted', $deliverable, $deliverable->event_id, [
            'commitment' => $deliverable->commitment,
        ]);

        // مدرک روی دیسک برای ممیزی می‌ماند (مثل رسید بودجه)
        $deliverable->delete();
    }

    /**
     * @param  array{sponsor_id?: ?int, status?: ?string, overdue?: bool}  $filters
     *                                                                                status: 'open' (default) | 'all' | a DeliverableStatus value
     * @return Collection<int, SponsorDeliverable>
     */
    public function deliverables(Event $event, array $filters = []): Collection
    {
        $query = SponsorDeliverable::query()->forEvent($event)
            ->with(['sponsor', 'asset', 'task'])
            ->orderByRaw('due_at IS NULL')
            ->orderBy('due_at')
            ->orderBy('id');

        $status = $filters['status'] ?? 'open';
        if ($status === 'open' || $status === null || $status === '') {
            $query->open();
        } elseif ($status !== 'all' && DeliverableStatus::tryFrom((string) $status) !== null) {
            $query->where('status', $status);
        }

        if (! empty($filters['sponsor_id'])) {
            $query->where('sponsor_id', (int) $filters['sponsor_id']);
        }

        if (! empty($filters['overdue'])) {
            $query->overdue();
        }

        return $query->get();
    }

    // ---------------------------------------------------------------- evidence

    /**
     * مدرک تحویل روی دیسک خصوصی؛ مدرک قبلی جایگزین (و پاک) می‌شود.
     *
     * @throws InvalidArgumentException
     */
    public function attachEvidence(SponsorDeliverable $deliverable, User $actor, UploadedFile $file): SponsorDeliverable
    {
        $event = $deliverable->event;
        $this->assertWritable($event, $actor);

        $extension = strtolower($file->getClientOriginalExtension());
        $allowed = (array) config('sponsorship.evidence_mimes', ['pdf', 'jpg', 'jpeg', 'png']);
        $maxKb = (int) config('sponsorship.evidence_max_kb', 5120);

        if (! in_array($extension, $allowed, true) || (int) $file->getSize() > $maxKb * 1024 || (int) $file->getSize() <= 0) {
            throw new InvalidArgumentException(__('sponsorship.evidence_invalid', [
                'types' => strtoupper(implode('، ', $allowed)),
                'max' => fa_num(intdiv($maxKb, 1024)),
            ]));
        }

        $path = $file->store('sponsor-evidence/'.$event->id, 'local');

        if (! is_string($path)) {
            throw new InvalidArgumentException(__('sponsorship.evidence_store_failed'));
        }

        $old = $deliverable->evidence_path;
        $deliverable->update(['evidence_path' => $path]);

        if ($old !== null && $old !== $path) {
            Storage::disk('local')->delete($old);
        }

        self::logActivity('sponsorship.evidence_added', $deliverable, $event->id, []);

        return $deliverable->refresh();
    }

    public function removeEvidence(SponsorDeliverable $deliverable, User $actor): SponsorDeliverable
    {
        $this->assertWritable($deliverable->event, $actor);

        $old = $deliverable->evidence_path;

        if ($old !== null) {
            $deliverable->update(['evidence_path' => null]);
            Storage::disk('local')->delete($old);

            self::logActivity('sponsorship.evidence_removed', $deliverable, $deliverable->event_id, []);
        }

        return $deliverable->refresh();
    }

    // ------------------------------------------------------------- task link

    /**
     * Creates a task (module = sponsorship, phase = during) for the deliverable and links it.
     * The caller authorizes TaskPolicy@create. Plan limits (tasks_per_event) still apply.
     *
     * @param  array<string, mixed>  $data  title?, committee_id?, assignee_id?
     *
     * @throws InvalidArgumentException
     * @throws LimitExceededException
     */
    public function createDeliverableTask(SponsorDeliverable $deliverable, User $actor, array $data = []): Task
    {
        $event = $deliverable->event;
        $this->assertWritable($event, $actor);
        $this->assertTaskLinkable($deliverable);

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            $title = mb_substr(
                __('sponsorship.task_prefix').' '.($deliverable->sponsor?->name ?? '').' — '.$deliverable->commitment,
                0,
                TaskService::MAX_TITLE_LENGTH,
            );
        }

        return DB::transaction(function () use ($deliverable, $actor, $data, $title) {
            $task = $this->tasks->create($deliverable->event, $actor, [
                'title' => $title,
                'description' => __('sponsorship.task_description', [
                    'sponsor' => $deliverable->sponsor?->name ?? '',
                    'commitment' => $deliverable->commitment,
                ]),
                'module' => TaskModule::Sponsorship,
                'phase' => TaskPhase::During,
                'priority' => TaskPriority::High,
                'committee_id' => $data['committee_id'] ?? null,
                'assignee_id' => $data['assignee_id'] ?? null,
                'due_at' => $deliverable->due_at?->copy()->endOfDay(),
            ]);

            $deliverable->update(['task_id' => $task->id]);

            self::logActivity('sponsorship.task_linked', $deliverable, $deliverable->event_id, [
                'task_id' => $task->id,
                'created' => true,
            ]);

            return $task;
        });
    }

    /**
     * Links an existing task of the SAME event.
     *
     * @throws InvalidArgumentException
     */
    public function linkTask(SponsorDeliverable $deliverable, User $actor, Task $task): void
    {
        $this->assertWritable($deliverable->event, $actor);
        $this->assertTaskLinkable($deliverable);

        if ((int) $task->event_id !== (int) $deliverable->event_id || $task->trashed()) {
            throw new InvalidArgumentException(__('sponsorship.task_invalid'));
        }

        $deliverable->update(['task_id' => $task->id]);

        self::logActivity('sponsorship.task_linked', $deliverable, $deliverable->event_id, ['task_id' => $task->id]);
    }

    public function unlinkTask(SponsorDeliverable $deliverable, User $actor): void
    {
        $this->assertWritable($deliverable->event, $actor);

        if ($deliverable->task_id !== null) {
            $taskId = $deliverable->task_id;
            $deliverable->update(['task_id' => null]);

            self::logActivity('sponsorship.task_unlinked', $deliverable, $deliverable->event_id, ['task_id' => $taskId]);
        }
    }

    // --------------------------------------------------------- summary/alerts

    /**
     * @return array{
     *     counts: array<string, int>,
     *     sponsor_total: int,
     *     committed_cash: int,
     *     committed_in_kind: int,
     *     committed_total: int,
     *     pipeline_total: int,
     *     deliverables: array{open: int, delivered: int, overdue: int, due_soon: int},
     *     delivery_percent: ?int
     * }
     */
    public function summary(Event $event): array
    {
        $sponsors = Sponsor::query()->forEvent($event)->get(['id', 'status', 'cash_value', 'in_kind_value']);

        $counts = [];
        foreach (SponsorStatus::cases() as $status) {
            $counts[$status->value] = 0;
        }

        $cash = 0;
        $inKind = 0;
        $pipeline = 0;

        foreach ($sponsors as $sponsor) {
            $counts[$sponsor->status->value]++;

            if ($sponsor->status->isCommitted()) {
                $cash += (int) $sponsor->cash_value;
                $inKind += (int) $sponsor->in_kind_value;
            } elseif ($sponsor->status->isPipeline()) {
                $pipeline += $sponsor->totalValue();
            }
        }

        $deliverables = SponsorDeliverable::query()->forEvent($event)->get(['id', 'status', 'due_at']);
        $open = $deliverables->filter(fn (SponsorDeliverable $d) => $d->status->isOpen());
        $delivered = $deliverables->filter(fn (SponsorDeliverable $d) => $d->status === DeliverableStatus::Delivered)->count();
        $dueSoonDays = (int) config('sponsorship.due_soon_days', 7);

        $base = $open->count() + $delivered;

        return [
            'counts' => $counts,
            'sponsor_total' => $sponsors->count(),
            'committed_cash' => $cash,
            'committed_in_kind' => $inKind,
            'committed_total' => $cash + $inKind,
            'pipeline_total' => $pipeline,
            'deliverables' => [
                'open' => $open->count(),
                'delivered' => $delivered,
                'overdue' => $open->filter(fn (SponsorDeliverable $d) => $d->isOverdue())->count(),
                'due_soon' => $open->filter(fn (SponsorDeliverable $d) => $d->isDueSoon($dueSoonDays))->count(),
            ],
            'delivery_percent' => $base === 0 ? null : intdiv($delivered * 100, $base),
        ];
    }

    /**
     * هشدار موعد: تعهدهای معوق و تعهدهای نزدیک به موعد (قدیمی‌ترین موعد اول).
     *
     * @return array{overdue: Collection<int, SponsorDeliverable>, due_soon: Collection<int, SponsorDeliverable>}
     */
    public function alerts(Event $event): array
    {
        $days = (int) config('sponsorship.due_soon_days', 7);
        $today = now()->startOfDay();

        $overdue = SponsorDeliverable::query()->forEvent($event)->overdue()
            ->with(['sponsor', 'asset'])->orderBy('due_at')->orderBy('id')->get();

        $dueSoon = SponsorDeliverable::query()->forEvent($event)->open()
            ->whereNotNull('due_at')
            ->where('due_at', '>=', $today->toDateString())
            ->where('due_at', '<=', $today->copy()->addDays($days)->toDateString())
            ->with(['sponsor', 'asset'])->orderBy('due_at')->orderBy('id')->get();

        return ['overdue' => $overdue, 'due_soon' => $dueSoon];
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    private function sponsorPayload(Event $event, array $data, bool $creating): array
    {
        $payload = [];

        if ($creating || array_key_exists('name', $data)) {
            $payload['name'] = $this->requiredName($data['name'] ?? '', 'sponsorship.name_required');
        }

        if ($creating || array_key_exists('category', $data)) {
            $payload['category'] = $this->enumCase(SponsorCategory::class, $data['category'] ?? null)
                ?? SponsorCategory::Other;
        }

        if (array_key_exists('contact_name', $data)) {
            $payload['contact_name'] = $this->cleanText($data['contact_name'], 120);
        }

        if (array_key_exists('contact_phone', $data)) {
            $payload['contact_phone'] = $this->contactPhone($data['contact_phone']);
        }

        foreach (['cash_value', 'in_kind_value'] as $key) {
            if ($creating || array_key_exists($key, $data)) {
                $payload[$key] = $this->amount($data[$key] ?? 0);
            }
        }

        if (array_key_exists('notes', $data)) {
            $payload['notes'] = $this->cleanText($data['notes'], self::MAX_TEXT_LENGTH);
        }

        if (array_key_exists('package_id', $data)) {
            $payload['package_id'] = $this->resolvePackage($event, $data['package_id'])?->id;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    private function assetPayload(array $data, bool $creating): array
    {
        $payload = [];

        if ($creating || array_key_exists('name', $data)) {
            $payload['name'] = $this->requiredName($data['name'] ?? '', 'sponsorship.asset_name_required');
        }

        if ($creating || array_key_exists('type', $data)) {
            $payload['type'] = $this->enumCase(AssetType::class, $data['type'] ?? null) ?? AssetType::Other;
        }

        if ($creating || array_key_exists('inventory', $data)) {
            $payload['inventory'] = $this->quantity($data['inventory'] ?? 1);
        }

        if ($creating || array_key_exists('price', $data)) {
            $payload['price'] = $this->amount($data['price'] ?? 0);
        }

        if (array_key_exists('description', $data)) {
            $payload['description'] = $this->cleanText($data['description'], self::MAX_TEXT_LENGTH);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    private function packagePayload(array $data, bool $creating): array
    {
        $payload = [];

        if ($creating || array_key_exists('name', $data)) {
            $payload['name'] = $this->requiredName($data['name'] ?? '', 'sponsorship.package_name_required', 120);
        }

        if ($creating || array_key_exists('price', $data)) {
            $payload['price'] = $this->amount($data['price'] ?? 0);
        }

        if (array_key_exists('description', $data)) {
            $payload['description'] = $this->cleanText($data['description'], self::MAX_TEXT_LENGTH);
        }

        return $payload;
    }

    /**
     * @return array<int, array{quantity: int}> برای sync()
     *
     * @throws InvalidArgumentException
     */
    private function resolvePackageAssets(Event $event, mixed $assets): array
    {
        if (! is_array($assets)) {
            return [];
        }

        $sync = [];

        foreach ($assets as $assetId => $quantity) {
            $asset = $this->resolveAsset($event, $assetId);

            if ($asset === null) {
                throw new InvalidArgumentException(__('sponsorship.asset_invalid'));
            }

            $sync[$asset->id] = ['quantity' => $this->quantity($quantity)];
        }

        return $sync;
    }

    private function resolveAsset(Event $event, mixed $assetId): ?SponsorshipAsset
    {
        if ($assetId === null || $assetId === '') {
            return null;
        }

        $asset = is_numeric($assetId)
            ? SponsorshipAsset::query()->forEvent($event)->whereKey((int) $assetId)->first()
            : null;

        if ($asset === null) {
            throw new InvalidArgumentException(__('sponsorship.asset_invalid'));
        }

        return $asset;
    }

    private function resolvePackage(Event $event, mixed $packageId): ?SponsorshipPackage
    {
        if ($packageId === null || $packageId === '') {
            return null;
        }

        $package = is_numeric($packageId)
            ? SponsorshipPackage::query()->forEvent($event)->whereKey((int) $packageId)->first()
            : null;

        if ($package === null) {
            throw new InvalidArgumentException(__('sponsorship.package_invalid'));
        }

        return $package;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function assertInventory(?SponsorshipAsset $asset, int $quantity, ?int $exceptDeliverableId): void
    {
        if ($asset === null) {
            return;
        }

        $allocated = (int) $asset->deliverables()
            ->where('status', '!=', DeliverableStatus::Cancelled->value)
            ->when($exceptDeliverableId !== null, fn ($q) => $q->where('id', '!=', $exceptDeliverableId))
            ->sum('quantity');

        if ($allocated + $quantity > (int) $asset->inventory) {
            throw new InvalidArgumentException(__('sponsorship.inventory_exceeded', [
                'asset' => $asset->name,
                'remaining' => fa_num(max(0, (int) $asset->inventory - $allocated)),
            ]));
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    private function assertTaskLinkable(SponsorDeliverable $deliverable): void
    {
        if ($deliverable->status === DeliverableStatus::Cancelled) {
            throw new InvalidArgumentException(__('sponsorship.deliverable_cancelled'));
        }

        // وظیفه‌ی soft-delete‌شده null برمی‌گردد و پیوند قدیمی مانع نیست
        if ($deliverable->task !== null) {
            throw new InvalidArgumentException(__('sponsorship.task_already_linked'));
        }
    }

    private function requiredName(mixed $value, string $requiredKey, int $max = self::MAX_NAME_LENGTH): string
    {
        $name = trim((string) $value);

        if ($name === '') {
            throw new InvalidArgumentException(__($requiredKey));
        }
        if (mb_strlen($name) > $max) {
            throw new InvalidArgumentException(__('sponsorship.name_too_long', ['max' => fa_num($max)]));
        }

        return $name;
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
            throw new InvalidArgumentException(__('sponsorship.text_too_long', ['max' => fa_num($max)]));
        }

        return $text;
    }

    /**
     * شماره‌ی تماس اسپانسر: موبایل یا ثابت؛ ارقام لاتین، با «+» اختیاری، ۵ تا ۱۸ رقم.
     *
     * @throws InvalidArgumentException
     */
    private function contactPhone(mixed $value): ?string
    {
        $raw = trim((string) $value);

        if ($raw === '') {
            return null;
        }

        $clean = (string) preg_replace('/[\s\-\(\)]+/', '', PhoneNumber::toLatinDigits($raw));

        if (preg_match('/^\+?\d{5,18}$/', $clean) !== 1) {
            throw new InvalidArgumentException(__('sponsorship.phone_invalid'));
        }

        return $clean;
    }

    /**
     * مبلغ ریالی صحیح و نامنفی.
     *
     * @throws InvalidArgumentException
     */
    private function amount(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_string($value) && preg_match('/^\d{1,16}$/', trim($value)) === 1) {
            $value = (int) trim($value);
        }

        if (! is_int($value) || $value < 0) {
            throw new InvalidArgumentException(__('sponsorship.amount_invalid'));
        }

        if ($value > (int) config('sponsorship.max_amount', 10_000_000_000_000)) {
            throw new InvalidArgumentException(__('sponsorship.amount_too_large'));
        }

        return $value;
    }

    /**
     * تعداد صحیح ۱ تا سقف.
     *
     * @throws InvalidArgumentException
     */
    private function quantity(mixed $value): int
    {
        if (is_string($value) && preg_match('/^\d{1,9}$/', trim($value)) === 1) {
            $value = (int) trim($value);
        }

        if (! is_int($value) || $value < 1 || $value > (int) config('sponsorship.max_inventory', 100_000)) {
            throw new InvalidArgumentException(__('sponsorship.quantity_invalid', [
                'max' => fa_num((int) config('sponsorship.max_inventory', 100_000)),
            ]));
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
            ?? throw new InvalidArgumentException(__('sponsorship.invalid_value'));
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->startOfDay();
        }

        try {
            return Carbon::parse((string) $value)->startOfDay();
        } catch (Throwable) {
            throw new InvalidArgumentException(__('sponsorship.date_invalid'));
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    private function assertWritable(Event $event, User $actor): void
    {
        if (! $this->hasFeature($actor)) {
            throw new InvalidArgumentException(__('sponsorship.feature_unavailable'));
        }

        if (in_array($event->status, [EventStatus::Archived, EventStatus::Cancelled], true)) {
            throw new InvalidArgumentException(__('sponsorship.event_closed'));
        }
    }
}
