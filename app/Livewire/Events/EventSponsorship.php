<?php

declare(strict_types=1);

namespace App\Livewire\Events;

use App\Enums\AssetType;
use App\Enums\DeliverableStatus;
use App\Enums\EventRole;
use App\Enums\EventStatus;
use App\Enums\SponsorCategory;
use App\Enums\SponsorStatus;
use App\Exceptions\LimitExceededException;
use App\Models\Event;
use App\Models\Sponsor;
use App\Models\SponsorDeliverable;
use App\Models\SponsorshipAsset;
use App\Models\SponsorshipPackage;
use App\Models\Task;
use App\Services\SponsorshipService;
use App\Support\Jalali;
use App\Support\Money;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * تب «اسپانسرینگ» در هاب رویداد (Step 11).
 *
 * دسترسی (SponsorPolicy):
 *  - viewModule: دیدن اسپانسرها، دارایی‌ها و تعهدها (مدیریت + رئیس کمیته + عضو)؛
 *    ارقام و اطلاعات تماس فقط برای سطح مدیریت نشان داده می‌شود.
 *  - manage: همه‌ی ساخت/ویرایش/حذف، تغییر وضعیت، مدرک و پیوند وظیفه.
 * هر اکشن Livewire جداگانه authorize می‌شود و هر شناسه فقط داخل همین رویداد پیدا می‌شود.
 * طرح حساب: ماژول فقط حساب سازمانی (`sponsorship`)؛ حساب عادی فقط پیام ارتقا می‌بیند.
 */
class EventSponsorship extends Component
{
    use WithFileUploads;

    public Event $event;

    public string $section = 'overview';

    public string $unit = Money::RIAL;

    // ---- فیلترها
    public string $filterSponsorStatus = '';

    public string $filterDeliverables = 'open';

    public string $filterDeliverableSponsor = '';

    // ---- فرم اسپانسر
    public bool $showSponsorForm = false;

    public ?int $editingSponsorId = null;

    public string $sName = '';

    public string $sCategory = 'other';

    public string $sContactName = '';

    public string $sContactPhone = '';

    public string $sCash = '';

    public string $sInKind = '';

    public string $sPackageId = '';

    public string $sNotes = '';

    // ---- فرم دارایی
    public bool $showAssetForm = false;

    public ?int $editingAssetId = null;

    public string $aName = '';

    public string $aType = 'naming';

    public string $aInventory = '1';

    public string $aPrice = '';

    public string $aDescription = '';

    // ---- فرم بسته
    public bool $showPackageForm = false;

    public ?int $editingPackageId = null;

    public string $pName = '';

    public string $pPrice = '';

    public string $pDescription = '';

    /** @var array<int|string, string> assetId ← تعداد در بسته (خالی = در بسته نیست) */
    public array $pQty = [];

    // ---- فرم تعهد
    public bool $showDeliverableForm = false;

    public ?int $editingDeliverableId = null;

    public string $dSponsorId = '';

    public string $dCommitment = '';

    public string $dAssetId = '';

    public string $dQuantity = '1';

    public string $dDue = '';

    // ---- جزئیات تعهد: وظیفه‌ی روز رویداد و مدرک
    public ?int $expandedDeliverableId = null;

    public string $ctTitle = '';

    public string $ctCommitteeId = '';

    public string $ctAssigneeId = '';

    public string $linkCommitteeId = '';

    public string $ctTaskId = '';

    public $evidence = null;

    public ?string $error = null;

    public ?string $notice = null;

    public function mount(Event $event): void
    {
        $this->authorize('view', $event);
        $this->event = $event;
    }

    // ------------------------------------------------------------ navigation

    public function setSection(string $section): void
    {
        $this->section = in_array($section, ['overview', 'sponsors', 'assets', 'deliverables'], true) ? $section : 'overview';
        $this->clearMessages();
    }

    public function setUnit(string $unit): void
    {
        $this->unit = Money::normalizeUnit($unit);

        // مبلغ‌های تایپ‌شده با واحد قبلی معنی نمی‌دهند
        $this->resetSponsorForm();
        $this->resetAssetForm();
        $this->resetPackageForm();
    }

    // --------------------------------------------------------------- sponsors

    public function openSponsorForm(): void
    {
        $this->authorize('manage', [Sponsor::class, $this->event]);
        $this->resetSponsorForm();
        $this->section = 'sponsors';
        $this->showSponsorForm = true;
    }

    public function cancelSponsorForm(): void
    {
        $this->resetSponsorForm();
    }

    public function editSponsor(int $id): void
    {
        $this->authorize('manage', [Sponsor::class, $this->event]);

        $sponsor = $this->findSponsor($id);

        $this->clearMessages();
        $this->editingSponsorId = $sponsor->id;
        $this->sName = $sponsor->name;
        $this->sCategory = $sponsor->category->value;
        $this->sContactName = (string) $sponsor->contact_name;
        $this->sContactPhone = (string) $sponsor->contact_phone;
        $this->sCash = $this->moneyInput((int) $sponsor->cash_value);
        $this->sInKind = $this->moneyInput((int) $sponsor->in_kind_value);
        $this->sPackageId = (string) ($sponsor->package_id ?? '');
        $this->sNotes = (string) $sponsor->notes;
        $this->section = 'sponsors';
        $this->showSponsorForm = true;
    }

    public function saveSponsor(SponsorshipService $service): void
    {
        $this->authorize('manage', [Sponsor::class, $this->event]);
        $this->clearMessages();

        $this->validate([
            'sName' => ['required', 'string', 'max:'.SponsorshipService::MAX_NAME_LENGTH],
            'sCategory' => ['required', 'in:'.implode(',', array_column(SponsorCategory::cases(), 'value'))],
            'sContactName' => ['nullable', 'string', 'max:120'],
            'sContactPhone' => ['nullable', 'string', 'max:25'],
            'sCash' => ['nullable', 'string', 'max:30'],
            'sInKind' => ['nullable', 'string', 'max:30'],
            'sPackageId' => ['nullable', 'string'],
            'sNotes' => ['nullable', 'string', 'max:'.SponsorshipService::MAX_TEXT_LENGTH],
        ], [], [
            'sName' => __('sponsorship.fields.name'),
            'sCategory' => __('sponsorship.fields.category'),
            'sContactName' => __('sponsorship.fields.contact_name'),
            'sContactPhone' => __('sponsorship.fields.contact_phone'),
            'sCash' => __('sponsorship.fields.cash_value'),
            'sInKind' => __('sponsorship.fields.in_kind_value'),
            'sNotes' => __('sponsorship.fields.notes'),
        ]);

        $cash = $this->parseMoney($this->sCash, 'sCash');
        $inKind = $this->parseMoney($this->sInKind, 'sInKind');

        if ($cash === null || $inKind === null) {
            return;
        }

        $data = [
            'name' => $this->sName,
            'category' => $this->sCategory,
            'contact_name' => $this->sContactName,
            'contact_phone' => $this->sContactPhone,
            'cash_value' => $cash,
            'in_kind_value' => $inKind,
            'package_id' => $this->sPackageId !== '' ? $this->sPackageId : null,
            'notes' => $this->sNotes,
        ];

        try {
            if ($this->editingSponsorId !== null) {
                $service->updateSponsor($this->findSponsor($this->editingSponsorId), Auth::user(), $data);
                $this->notice = __('sponsorship.sponsor_updated');
            } else {
                $service->createSponsor($this->event, Auth::user(), $data);
                $this->notice = __('sponsorship.sponsor_created');
            }

            $this->resetSponsorForm();
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function changeSponsorStatus(int $id, string $status, SponsorshipService $service): void
    {
        $this->authorize('manage', [Sponsor::class, $this->event]);
        $this->clearMessages();

        $case = SponsorStatus::tryFrom($status);
        if ($case === null) {
            $this->error = __('sponsorship.invalid_value');

            return;
        }

        try {
            $service->transitionSponsor($this->findSponsor($id), Auth::user(), $case);
            $this->notice = __('sponsorship.sponsor_status_changed');
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function deleteSponsor(int $id, SponsorshipService $service): void
    {
        $this->authorize('manage', [Sponsor::class, $this->event]);
        $this->clearMessages();

        try {
            $service->deleteSponsor($this->findSponsor($id), Auth::user());
            $this->notice = __('sponsorship.sponsor_deleted');
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function generateFromPackage(int $sponsorId, SponsorshipService $service): void
    {
        $this->authorize('manage', [Sponsor::class, $this->event]);
        $this->clearMessages();

        try {
            $created = $service->generateDeliverablesFromPackage($this->findSponsor($sponsorId), Auth::user());

            $this->notice = $created->isEmpty()
                ? __('sponsorship.generate_nothing_new')
                : __('sponsorship.generate_done', ['count' => fa_num($created->count())]);
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    // ----------------------------------------------------------------- assets

    public function openAssetForm(): void
    {
        $this->authorize('manage', [Sponsor::class, $this->event]);
        $this->resetAssetForm();
        $this->section = 'assets';
        $this->showAssetForm = true;
    }

    public function cancelAssetForm(): void
    {
        $this->resetAssetForm();
    }

    public function editAsset(int $id): void
    {
        $this->authorize('manage', [Sponsor::class, $this->event]);

        $asset = $this->findAsset($id);

        $this->clearMessages();
        $this->editingAssetId = $asset->id;
        $this->aName = $asset->name;
        $this->aType = $asset->type->value;
        $this->aInventory = (string) $asset->inventory;
        $this->aPrice = $this->moneyInput((int) $asset->price);
        $this->aDescription = (string) $asset->description;
        $this->section = 'assets';
        $this->showAssetForm = true;
    }

    public function saveAsset(SponsorshipService $service): void
    {
        $this->authorize('manage', [Sponsor::class, $this->event]);
        $this->clearMessages();

        $this->validate([
            'aName' => ['required', 'string', 'max:'.SponsorshipService::MAX_NAME_LENGTH],
            'aType' => ['required', 'in:'.implode(',', array_column(AssetType::cases(), 'value'))],
            'aInventory' => ['required', 'string', 'max:9'],
            'aPrice' => ['nullable', 'string', 'max:30'],
            'aDescription' => ['nullable', 'string', 'max:'.SponsorshipService::MAX_TEXT_LENGTH],
        ], [], [
            'aName' => __('sponsorship.fields.asset_name'),
            'aType' => __('sponsorship.fields.asset_type'),
            'aInventory' => __('sponsorship.fields.inventory'),
            'aPrice' => __('sponsorship.fields.price'),
            'aDescription' => __('sponsorship.fields.asset_description'),
        ]);

        $price = $this->parseMoney($this->aPrice, 'aPrice');

        if ($price === null) {
            return;
        }

        $data = [
            'name' => $this->aName,
            'type' => $this->aType,
            'inventory' => PhoneNumber::toLatinDigits(trim($this->aInventory)),
            'price' => $price,
            'description' => $this->aDescription,
        ];

        try {
            if ($this->editingAssetId !== null) {
                $service->updateAsset($this->findAsset($this->editingAssetId), Auth::user(), $data);
                $this->notice = __('sponsorship.asset_updated');
            } else {
                $service->createAsset($this->event, Auth::user(), $data);
                $this->notice = __('sponsorship.asset_created');
            }

            $this->resetAssetForm();
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function deleteAsset(int $id, SponsorshipService $service): void
    {
        $this->authorize('manage', [Sponsor::class, $this->event]);
        $this->clearMessages();

        try {
            $service->deleteAsset($this->findAsset($id), Auth::user());
            $this->notice = __('sponsorship.asset_deleted');
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    // --------------------------------------------------------------- packages

    public function openPackageForm(): void
    {
        $this->authorize('manage', [Sponsor::class, $this->event]);
        $this->resetPackageForm();
        $this->section = 'assets';
        $this->showPackageForm = true;
    }

    public function cancelPackageForm(): void
    {
        $this->resetPackageForm();
    }

    public function editPackage(int $id): void
    {
        $this->authorize('manage', [Sponsor::class, $this->event]);

        $package = $this->findPackage($id);

        $this->clearMessages();
        $this->editingPackageId = $package->id;
        $this->pName = $package->name;
        $this->pPrice = $this->moneyInput((int) $package->price);
        $this->pDescription = (string) $package->description;
        $this->pQty = [];

        foreach ($package->assets as $asset) {
            $this->pQty[$asset->id] = (string) $asset->pivot->quantity;
        }

        $this->section = 'assets';
        $this->showPackageForm = true;
    }

    public function savePackage(SponsorshipService $service): void
    {
        $this->authorize('manage', [Sponsor::class, $this->event]);
        $this->clearMessages();

        $this->validate([
            'pName' => ['required', 'string', 'max:120'],
            'pPrice' => ['nullable', 'string', 'max:30'],
            'pDescription' => ['nullable', 'string', 'max:'.SponsorshipService::MAX_TEXT_LENGTH],
            'pQty' => ['array'],
            'pQty.*' => ['nullable', 'string', 'max:9'],
        ], [], [
            'pName' => __('sponsorship.fields.package_name'),
            'pPrice' => __('sponsorship.fields.package_price'),
            'pDescription' => __('sponsorship.fields.package_description'),
        ]);

        $price = $this->parseMoney($this->pPrice, 'pPrice');

        if ($price === null) {
            return;
        }

        // فقط ردیف‌هایی که تعداد دارند در بسته‌اند
        $assets = [];
        foreach ($this->pQty as $assetId => $qty) {
            $qty = PhoneNumber::toLatinDigits(trim((string) $qty));

            if ($qty !== '' && $qty !== '0') {
                $assets[(int) $assetId] = $qty;
            }
        }

        $data = [
            'name' => $this->pName,
            'price' => $price,
            'description' => $this->pDescription,
            'assets' => $assets,
        ];

        try {
            if ($this->editingPackageId !== null) {
                $service->updatePackage($this->findPackage($this->editingPackageId), Auth::user(), $data);
                $this->notice = __('sponsorship.package_updated');
            } else {
                $service->createPackage($this->event, Auth::user(), $data);
                $this->notice = __('sponsorship.package_created');
            }

            $this->resetPackageForm();
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function deletePackage(int $id, SponsorshipService $service): void
    {
        $this->authorize('manage', [Sponsor::class, $this->event]);
        $this->clearMessages();

        try {
            $service->deletePackage($this->findPackage($id), Auth::user());
            $this->notice = __('sponsorship.package_deleted');
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    // ----------------------------------------------------------- deliverables

    public function openDeliverableForm(string $sponsorId = ''): void
    {
        $this->authorize('manage', [Sponsor::class, $this->event]);
        $this->resetDeliverableForm();
        $this->section = 'deliverables';
        $this->dSponsorId = ctype_digit($sponsorId) ? $sponsorId : '';
        $this->showDeliverableForm = true;
    }

    public function cancelDeliverableForm(): void
    {
        $this->resetDeliverableForm();
    }

    public function editDeliverable(int $id): void
    {
        $this->authorize('manage', [Sponsor::class, $this->event]);

        $deliverable = $this->findDeliverable($id);

        $this->clearMessages();
        $this->editingDeliverableId = $deliverable->id;
        $this->dSponsorId = (string) $deliverable->sponsor_id;
        $this->dCommitment = $deliverable->commitment;
        $this->dAssetId = (string) ($deliverable->asset_id ?? '');
        $this->dQuantity = (string) $deliverable->quantity;
        $this->dDue = $deliverable->due_at?->toDateString() ?? '';
        $this->section = 'deliverables';
        $this->showDeliverableForm = true;
    }

    public function saveDeliverable(SponsorshipService $service): void
    {
        $this->authorize('manage', [Sponsor::class, $this->event]);
        $this->clearMessages();

        $this->validate([
            'dSponsorId' => ['required', 'integer'],
            'dCommitment' => ['nullable', 'string', 'max:'.SponsorshipService::MAX_COMMITMENT_LENGTH],
            'dAssetId' => ['nullable', 'string'],
            'dQuantity' => ['required', 'string', 'max:9'],
            'dDue' => ['nullable', 'string', 'max:20'],
        ], [], [
            'dSponsorId' => __('sponsorship.fields.sponsor'),
            'dCommitment' => __('sponsorship.fields.commitment'),
            'dAssetId' => __('sponsorship.fields.asset'),
            'dQuantity' => __('sponsorship.fields.quantity'),
            'dDue' => __('sponsorship.fields.due_at'),
        ]);

        $due = null;
        if (trim($this->dDue) !== '') {
            $due = Jalali::parse($this->dDue);

            if ($due === null) {
                $this->addError('dDue', __('sponsorship.date_invalid'));

                return;
            }
        }

        $data = [
            'commitment' => $this->dCommitment,
            'asset_id' => $this->dAssetId !== '' ? $this->dAssetId : null,
            'quantity' => PhoneNumber::toLatinDigits(trim($this->dQuantity)),
            'due_at' => $due,
        ];

        try {
            if ($this->editingDeliverableId !== null) {
                $service->updateDeliverable($this->findDeliverable($this->editingDeliverableId), Auth::user(), $data);
                $this->notice = __('sponsorship.deliverable_updated');
            } else {
                $service->addDeliverable($this->findSponsor((int) $this->dSponsorId), Auth::user(), $data);
                $this->notice = __('sponsorship.deliverable_created');
            }

            $this->resetDeliverableForm();
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function changeDeliverableStatus(int $id, string $status, SponsorshipService $service): void
    {
        $this->authorize('manage', [Sponsor::class, $this->event]);
        $this->clearMessages();

        $case = DeliverableStatus::tryFrom($status);
        if ($case === null) {
            $this->error = __('sponsorship.invalid_value');

            return;
        }

        try {
            $service->setDeliverableStatus($this->findDeliverable($id), Auth::user(), $case);
            $this->notice = __('sponsorship.deliverable_status_changed');
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function deleteDeliverable(int $id, SponsorshipService $service): void
    {
        $this->authorize('manage', [Sponsor::class, $this->event]);
        $this->clearMessages();

        try {
            $service->deleteDeliverable($this->findDeliverable($id), Auth::user());

            if ($this->expandedDeliverableId === $id) {
                $this->expandedDeliverableId = null;
            }

            $this->notice = __('sponsorship.deliverable_deleted');
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function toggleDeliverable(int $id): void
    {
        // فقط داخل همین رویداد پیدا می‌شود
        $deliverable = $this->findDeliverable($id);

        $this->expandedDeliverableId = $this->expandedDeliverableId === $deliverable->id ? null : $deliverable->id;
        $this->ctTitle = '';
        $this->ctCommitteeId = '';
        $this->ctAssigneeId = '';
        $this->linkCommitteeId = '';
        $this->ctTaskId = '';
        $this->evidence = null;
        $this->resetValidation();
        $this->clearMessages();
    }

    // --------------------------------------------------------------- evidence

    public function saveEvidence(int $id, SponsorshipService $service): void
    {
        $this->authorize('manage', [Sponsor::class, $this->event]);
        $this->clearMessages();

        $deliverable = $this->findDeliverable($id);

        $this->validate([
            'evidence' => [
                'required',
                'file',
                'max:'.(int) config('sponsorship.evidence_max_kb', 5120),
                'mimes:'.implode(',', (array) config('sponsorship.evidence_mimes', ['pdf', 'jpg', 'jpeg', 'png'])),
            ],
        ], [], ['evidence' => __('sponsorship.fields.evidence')]);

        try {
            $service->attachEvidence($deliverable, Auth::user(), $this->evidence);
            $this->evidence = null;
            $this->notice = __('sponsorship.evidence_saved');
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function removeEvidence(int $id, SponsorshipService $service): void
    {
        $this->authorize('manage', [Sponsor::class, $this->event]);
        $this->clearMessages();

        try {
            $service->removeEvidence($this->findDeliverable($id), Auth::user());
            $this->notice = __('sponsorship.evidence_removed');
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function downloadEvidence(int $id): StreamedResponse
    {
        $this->authorize('manage', [Sponsor::class, $this->event]);

        $deliverable = $this->findDeliverable($id);

        abort_if($deliverable->evidence_path === null, 404);
        abort_unless(Storage::disk('local')->exists($deliverable->evidence_path), 404);

        $extension = pathinfo($deliverable->evidence_path, PATHINFO_EXTENSION);

        return Storage::disk('local')->download(
            $deliverable->evidence_path,
            'evidence-'.$deliverable->uuid.($extension !== '' ? '.'.$extension : ''),
        );
    }

    // ------------------------------------------------------------- task link

    public function createDeliverableTask(int $id, SponsorshipService $service): void
    {
        $this->authorize('manage', [Sponsor::class, $this->event]);
        $this->clearMessages();

        $this->validate([
            'ctTitle' => ['nullable', 'string', 'max:200'],
            'ctCommitteeId' => ['nullable', 'string'],
            'ctAssigneeId' => ['nullable', 'string'],
        ], [], [
            'ctTitle' => __('sponsorship.task_title'),
            'ctCommitteeId' => __('sponsorship.task_committee'),
            'ctAssigneeId' => __('sponsorship.task_assignee'),
        ]);

        $committee = null;

        if ($this->ctCommitteeId !== '') {
            // فقط کمیته‌ی همین رویداد
            $committee = ctype_digit($this->ctCommitteeId)
                ? $this->event->committees()->whereKey((int) $this->ctCommitteeId)->first()
                : null;

            if ($committee === null) {
                $this->error = __('sponsorship.committee_invalid');

                return;
            }
        }

        $this->authorize('create', [Task::class, $this->event, $committee]);

        try {
            $service->createDeliverableTask($this->findDeliverable($id), Auth::user(), [
                'title' => $this->ctTitle,
                'committee_id' => $committee?->id,
                'assignee_id' => $this->ctAssigneeId !== '' ? $this->ctAssigneeId : null,
            ]);

            $this->ctTitle = '';
            $this->ctCommitteeId = '';
            $this->ctAssigneeId = '';
            $this->notice = __('sponsorship.task_created');
        } catch (InvalidArgumentException|LimitExceededException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function linkDeliverableTask(int $id, SponsorshipService $service): void
    {
        $this->authorize('manage', [Sponsor::class, $this->event]);
        $this->clearMessages();

        if (! ctype_digit($this->ctTaskId)) {
            $this->error = __('sponsorship.task_invalid');

            return;
        }

        try {
            $task = $this->event->tasks()->whereKey((int) $this->ctTaskId)->first();

            if ($task === null) {
                throw new InvalidArgumentException(__('sponsorship.task_invalid'));
            }

            $service->linkTask($this->findDeliverable($id), Auth::user(), $task);
            $this->ctTaskId = '';
            $this->notice = __('sponsorship.task_linked');
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function unlinkDeliverableTask(int $id, SponsorshipService $service): void
    {
        $this->authorize('manage', [Sponsor::class, $this->event]);
        $this->clearMessages();

        try {
            $service->unlinkTask($this->findDeliverable($id), Auth::user());
            $this->notice = __('sponsorship.task_unlinked');
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    // ----------------------------------------------------------------- render

    public function render(SponsorshipService $service)
    {
        $user = Auth::user();

        $feature = $service->hasFeature($user);
        $canManage = $user->can('manage', [Sponsor::class, $this->event]);
        $canView = $user->can('viewModule', [Sponsor::class, $this->event]);
        $closed = in_array($this->event->status, [EventStatus::Archived, EventStatus::Cancelled], true);

        $data = [
            'feature' => $feature,
            'canManage' => $canManage,
            'canView' => $canView,
            'canEdit' => $canManage && $feature && ! $closed,
            'closed' => $closed,
            'summary' => null,
            'alerts' => ['overdue' => collect(), 'due_soon' => collect()],
            'sponsors' => collect(),
            'assets' => collect(),
            'packages' => collect(),
            'deliverables' => collect(),
            'categories' => SponsorCategory::cases(),
            'statuses' => SponsorStatus::cases(),
            'assetTypes' => AssetType::cases(),
            'deliverableStatuses' => DeliverableStatus::cases(),
            'committees' => collect(),
            'linkableTasks' => collect(),
            'assignees' => collect(),
            'maxKb' => (int) config('sponsorship.evidence_max_kb', 5120),
        ];

        if (! $feature || ! $canView) {
            return view('livewire.events.event-sponsorship', $data);
        }

        $data['summary'] = $service->summary($this->event);
        $data['alerts'] = $service->alerts($this->event);
        $data['sponsors'] = $service->sponsors($this->event, ['status' => $this->filterSponsorStatus]);
        $data['assets'] = $service->assets($this->event);
        $data['packages'] = $service->packages($this->event);
        $data['deliverables'] = $service->deliverables($this->event, [
            'status' => $this->filterDeliverables,
            'sponsor_id' => ctype_digit($this->filterDeliverableSponsor) ? (int) $this->filterDeliverableSponsor : null,
        ]);

        if ($canManage && $this->expandedDeliverableId !== null) {
            $data['committees'] = $this->event->committees()->get(['id', 'name']);
            $data['assignees'] = $this->event->members()->get()
                ->filter(fn ($u) => $u->pivot->role !== EventRole::Viewer->value)
                ->values();

            $linkable = $this->event->tasks()->orderByDesc('id');

            if ($this->linkCommitteeId === 'none') {
                $linkable->whereNull('committee_id');
            } elseif (ctype_digit($this->linkCommitteeId)) {
                $linkable->where('committee_id', (int) $this->linkCommitteeId);
            }

            $data['linkableTasks'] = $linkable->limit(100)->get(['id', 'title', 'committee_id']);
        }

        return view('livewire.events.event-sponsorship', $data);
    }

    // -------------------------------------------------------------- internals

    private function findSponsor(int $id): Sponsor
    {
        return Sponsor::query()->forEvent($this->event)->whereKey($id)->firstOrFail();
    }

    private function findAsset(int $id): SponsorshipAsset
    {
        return SponsorshipAsset::query()->forEvent($this->event)->whereKey($id)->firstOrFail();
    }

    private function findPackage(int $id): SponsorshipPackage
    {
        return SponsorshipPackage::query()->forEvent($this->event)->whereKey($id)->firstOrFail();
    }

    private function findDeliverable(int $id): SponsorDeliverable
    {
        return SponsorDeliverable::query()->forEvent($this->event)->whereKey($id)->firstOrFail();
    }

    /**
     * ورودی خالی = ۰؛ نامعتبر ← خطای فیلد و null.
     */
    private function parseMoney(string $input, string $field): ?int
    {
        if (trim($input) === '') {
            return 0;
        }

        $amount = Money::parse($input, $this->unit);

        if ($amount === null) {
            $this->addError($field, __('sponsorship.amount_invalid'));

            return null;
        }

        return $amount;
    }

    /** مبلغ ریالی ← متن ورودی در واحد جاری (بدون جداکننده). */
    private function moneyInput(int $rial): string
    {
        if ($rial === 0) {
            return '';
        }

        return $this->unit === Money::TOMAN ? (string) intdiv($rial, 10) : (string) $rial;
    }

    private function clearMessages(): void
    {
        $this->error = null;
        $this->notice = null;
    }

    private function resetSponsorForm(): void
    {
        $this->reset([
            'showSponsorForm', 'editingSponsorId', 'sName', 'sContactName', 'sContactPhone',
            'sCash', 'sInKind', 'sPackageId', 'sNotes',
        ]);
        $this->sCategory = 'other';
        $this->resetValidation();
    }

    private function resetAssetForm(): void
    {
        $this->reset(['showAssetForm', 'editingAssetId', 'aName', 'aPrice', 'aDescription']);
        $this->aType = 'naming';
        $this->aInventory = '1';
        $this->resetValidation();
    }

    private function resetPackageForm(): void
    {
        $this->reset(['showPackageForm', 'editingPackageId', 'pName', 'pPrice', 'pDescription', 'pQty']);
        $this->resetValidation();
    }

    private function resetDeliverableForm(): void
    {
        $this->reset(['showDeliverableForm', 'editingDeliverableId', 'dSponsorId', 'dCommitment', 'dAssetId', 'dDue']);
        $this->dQuantity = '1';
        $this->resetValidation();
    }
}
