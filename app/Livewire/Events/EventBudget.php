<?php

declare(strict_types=1);

namespace App\Livewire\Events;

use App\Enums\BudgetCategory;
use App\Enums\BudgetLineType;
use App\Enums\CostBehavior;
use App\Enums\EventStatus;
use App\Models\BudgetLine;
use App\Models\Event;
use App\Models\Transaction;
use App\Services\BudgetService;
use App\Support\Jalali;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * تب «بودجه» در هاب رویداد (Step 09).
 *
 * دسترسی (BudgetLinePolicy):
 *  - manage: همه‌ی بخش‌ها و اکشن‌ها
 *  - viewSummary: فقط مجموع‌ها (رئیس کمیته / عضو)
 *  - دیگران: پیام «دسترسی ندارید»
 * هر اکشن Livewire جداگانه authorize می‌شود و هر شناسه فقط داخل همین رویداد پیدا می‌شود.
 */
class EventBudget extends Component
{
    use WithFileUploads;

    public Event $event;

    public string $section = 'overview';

    public string $unit = Money::RIAL;

    // ---- فرم خط بودجه
    public bool $showLineForm = false;

    public ?int $editingLineId = null;

    public string $lineType = 'expense';

    public string $lineCategory = 'venue';

    public string $lineName = '';

    public string $lineAmount = '';

    public string $lineBehavior = 'fixed';

    public string $lineCommitteeId = '';

    public string $lineAssumption = '';

    // ---- فرم تراکنش
    public bool $showTxForm = false;

    public ?int $editingTxId = null;

    public string $txLineId = '';

    public string $txAmount = '';

    public string $txDate = '';

    public string $txDescription = '';

    public bool $removeReceipt = false;

    /** @var mixed Livewire TemporaryUploadedFile|null */
    public $receipt = null;

    public string $txFilterLine = '';

    // ---- نسخه‌ها و تنظیمات
    public string $approveNote = '';

    public string $thresholdInput = '';

    public ?string $error = null;

    public ?string $notice = null;

    public function mount(Event $event): void
    {
        $this->authorize('view', $event);
        $this->event = $event;
        $this->thresholdInput = (string) app(BudgetService::class)->overrunThreshold($event);
    }

    // ------------------------------------------------------------ navigation

    public function setSection(string $section): void
    {
        $allowed = ['overview'];

        if ($this->can('manage')) {
            $allowed[] = 'lines';
            $allowed[] = 'transactions';

            if (app(BudgetService::class)->hasFull(Auth::user())) {
                $allowed[] = 'versions';
            }
        }

        $this->section = in_array($section, $allowed, true) ? $section : 'overview';
        $this->clearMessages();
    }

    public function setUnit(string $unit): void
    {
        $this->unit = Money::normalizeUnit($unit);
    }

    // ----------------------------------------------------------------- lines

    public function updatedLineType(string $value): void
    {
        $type = BudgetLineType::tryFrom($value) ?? BudgetLineType::Expense;
        $this->lineCategory = BudgetCategory::forType($type)[0]->value;
    }

    public function openLineForm(): void
    {
        $this->authorize('manage', [BudgetLine::class, $this->event]);
        $this->resetLineForm();
        $this->section = 'lines';
        $this->showLineForm = true;
    }

    public function cancelLineForm(): void
    {
        $this->resetLineForm();
    }

    public function editLine(int $id): void
    {
        $this->authorize('manage', [BudgetLine::class, $this->event]);

        $line = $this->findLine($id);

        $this->clearMessages();
        $this->editingLineId = $line->id;
        $this->lineType = $line->type->value;
        $this->lineCategory = $line->category->value;
        $this->lineName = $line->name;
        $this->lineAmount = $this->amountForInput($line->planned_amount);
        $this->lineBehavior = $line->cost_behavior->value;
        $this->lineCommitteeId = (string) ($line->committee_id ?? '');
        $this->lineAssumption = (string) $line->assumption;
        $this->showLineForm = true;
    }

    public function saveLine(BudgetService $service): void
    {
        $this->authorize('manage', [BudgetLine::class, $this->event]);
        $this->clearMessages();

        $this->validate([
            'lineType' => ['required', 'in:expense,revenue'],
            'lineCategory' => ['required', 'in:'.implode(',', array_column(BudgetCategory::cases(), 'value'))],
            'lineName' => ['required', 'string', 'max:160'],
            'lineAmount' => ['required', 'string', 'max:40'],
            'lineBehavior' => ['required', 'in:fixed,variable'],
            'lineCommitteeId' => ['nullable', 'integer'],
            'lineAssumption' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'lineType' => __('budget.fields.type'),
            'lineCategory' => __('budget.fields.category'),
            'lineName' => __('budget.fields.name'),
            'lineAmount' => __('budget.fields.planned_amount'),
            'lineBehavior' => __('budget.fields.cost_behavior'),
            'lineCommitteeId' => __('budget.fields.committee'),
            'lineAssumption' => __('budget.fields.assumption'),
        ]);

        $amount = Money::parse($this->lineAmount, $this->unit);

        if ($amount === null) {
            $this->addError('lineAmount', __('budget.amount_invalid'));

            return;
        }

        $data = [
            'type' => $this->lineType,
            'category' => $this->lineCategory,
            'name' => $this->lineName,
            'planned_amount' => $amount,
            'cost_behavior' => $this->lineBehavior,
            'committee_id' => $this->lineCommitteeId !== '' ? (int) $this->lineCommitteeId : null,
            'assumption' => $this->lineAssumption,
        ];

        try {
            if ($this->editingLineId !== null) {
                $service->updateLine($this->findLine($this->editingLineId), Auth::user(), $data);
                $this->notice = __('budget.line_updated');
            } else {
                $service->addLine($this->event, Auth::user(), $data);
                $this->notice = __('budget.line_created');
            }

            $this->resetLineForm();
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function deleteLine(int $id, BudgetService $service): void
    {
        $this->authorize('manage', [BudgetLine::class, $this->event]);
        $this->clearMessages();

        try {
            $service->deleteLine($this->findLine($id), Auth::user());
            $this->notice = __('budget.line_deleted');
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    // ---------------------------------------------------------- transactions

    public function openTxForm(?int $lineId = null): void
    {
        $this->authorize('manage', [BudgetLine::class, $this->event]);
        $this->resetTxForm();

        if ($lineId !== null) {
            $this->txLineId = (string) $this->findLine($lineId)->id;
        }

        $this->txDate = now()->toDateString();
        $this->section = 'transactions';
        $this->showTxForm = true;
    }

    public function cancelTxForm(): void
    {
        $this->resetTxForm();
    }

    public function editTx(int $id): void
    {
        $this->authorize('manage', [BudgetLine::class, $this->event]);

        $tx = $this->findTransaction($id);

        $this->clearMessages();
        $this->editingTxId = $tx->id;
        $this->txLineId = (string) $tx->budget_line_id;
        $this->txAmount = $this->amountForInput($tx->amount);
        $this->txDate = $tx->date->toDateString();
        $this->txDescription = (string) $tx->description;
        $this->removeReceipt = false;
        $this->receipt = null;
        $this->section = 'transactions';
        $this->showTxForm = true;
    }

    public function saveTx(BudgetService $service): void
    {
        $this->authorize('manage', [BudgetLine::class, $this->event]);
        $this->clearMessages();

        $this->validate([
            'txLineId' => ['required', 'integer'],
            'txAmount' => ['required', 'string', 'max:40'],
            'txDate' => ['required', 'string', 'max:20'],
            'txDescription' => ['nullable', 'string', 'max:255'],
            'receipt' => [
                'nullable',
                'file',
                'max:'.(int) config('budget.receipt_max_kb', 5120),
                'mimes:'.implode(',', (array) config('budget.receipt_mimes', ['pdf', 'jpg', 'jpeg', 'png'])),
            ],
        ], [], [
            'txLineId' => __('budget.fields.line'),
            'txAmount' => __('budget.fields.amount'),
            'txDate' => __('budget.fields.date'),
            'txDescription' => __('budget.fields.description'),
            'receipt' => __('budget.fields.receipt'),
        ]);

        $amount = Money::parse($this->txAmount, $this->unit);

        if ($amount === null) {
            $this->addError('txAmount', __('budget.amount_invalid'));

            return;
        }

        $date = Jalali::parse($this->txDate);

        if ($date === null) {
            $this->addError('txDate', __('budget.date_invalid'));

            return;
        }

        $data = [
            'amount' => $amount,
            'date' => $date,
            'description' => $this->txDescription,
        ];

        try {
            if ($this->editingTxId !== null) {
                $service->updateTransaction(
                    $this->findTransaction($this->editingTxId),
                    Auth::user(),
                    $data,
                    $this->receipt,
                    $this->removeReceipt,
                );
                $this->notice = __('budget.transaction_updated');
            } else {
                $service->recordTransaction(
                    $this->findLine((int) $this->txLineId),
                    Auth::user(),
                    $data,
                    $this->receipt,
                );
                $this->notice = __('budget.transaction_created');
            }

            $this->resetTxForm();
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function deleteTx(int $id, BudgetService $service): void
    {
        $this->authorize('manage', [BudgetLine::class, $this->event]);
        $this->clearMessages();

        try {
            $service->deleteTransaction($this->findTransaction($id), Auth::user());
            $this->notice = __('budget.transaction_deleted');
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function downloadReceipt(int $id): StreamedResponse
    {
        $this->authorize('manage', [BudgetLine::class, $this->event]);

        $tx = $this->findTransaction($id);

        abort_if($tx->receipt_path === null, 404);
        abort_unless(Storage::disk('local')->exists($tx->receipt_path), 404);

        $extension = pathinfo($tx->receipt_path, PATHINFO_EXTENSION);

        return Storage::disk('local')->download(
            $tx->receipt_path,
            'receipt-'.$tx->uuid.($extension !== '' ? '.'.$extension : ''),
        );
    }

    // -------------------------------------------------------------- versions

    public function approve(BudgetService $service): void
    {
        $this->authorize('approve', [BudgetLine::class, $this->event]);
        $this->clearMessages();

        try {
            $version = $service->approve($this->event, Auth::user(), $this->approveNote);
            $this->approveNote = '';
            $this->resetLineForm();
            $this->notice = __('budget.approved', ['number' => fa_num($version->number)]);
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function startRevision(BudgetService $service): void
    {
        $this->authorize('approve', [BudgetLine::class, $this->event]);
        $this->clearMessages();

        try {
            $version = $service->startRevision($this->event, Auth::user());
            $this->notice = __('budget.revision_started', ['number' => fa_num($version->number)]);
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function saveThreshold(BudgetService $service): void
    {
        $this->authorize('manage', [BudgetLine::class, $this->event]);
        $this->clearMessages();

        $this->validate([
            'thresholdInput' => ['required', 'integer', 'min:0', 'max:1000'],
        ], [], [
            'thresholdInput' => __('budget.fields.threshold'),
        ]);

        try {
            $service->setOverrunThreshold($this->event, Auth::user(), (int) $this->thresholdInput);
            $this->event->refresh();
            $this->notice = __('budget.threshold_saved');
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    // ---------------------------------------------------------------- render

    public function render(BudgetService $service)
    {
        $user = Auth::user();

        $canManage = $user->can('manage', [BudgetLine::class, $this->event]);
        $canSummary = $user->can('viewSummary', [BudgetLine::class, $this->event]);
        $full = $service->hasFull($user);
        $basic = $service->hasBasic($user);
        $closed = in_array($this->event->status, [EventStatus::Archived, EventStatus::Cancelled], true);

        $data = [
            'canManage' => $canManage,
            'canSummary' => $canSummary,
            'full' => $full,
            'basic' => $basic,
            'closed' => $closed,
            'totals' => null,
            'lines' => collect(),
            'locked' => false,
            'categories' => BudgetCategory::forType(BudgetLineType::tryFrom($this->lineType) ?? BudgetLineType::Expense),
            'behaviors' => CostBehavior::cases(),
            'committees' => collect(),
            'overrunLines' => [],
            'byCategory' => [],
            'byCommittee' => [],
            'breakEven' => null,
            'versions' => collect(),
            'latestVersion' => null,
            'workingNumber' => 1,
            'pending' => null,
            'transactions' => collect(),
            'maxKb' => (int) config('budget.receipt_max_kb', 5120),
        ];

        if (! $canSummary) {
            return view('livewire.events.event-budget', $data);
        }

        $lines = $service->lines($this->event);
        $data['totals'] = $service->totals($this->event, $lines);

        // اعضای غیرمدیر فقط مجموع‌ها را می‌بینند؛ جزئیات هرگز به view نمی‌رود
        if (! $canManage) {
            return view('livewire.events.event-budget', $data);
        }

        $data['lines'] = $lines;
        $data['locked'] = $service->isLocked($this->event);
        $data['workingNumber'] = $service->workingNumber($this->event);
        $data['overrunLines'] = $service->overrunLines($lines, $data['totals']['threshold']);
        $data['byCategory'] = $service->byCategory($lines);
        $data['transactions'] = $service->transactions(
            $this->event,
            $this->txFilterLine !== '' ? (int) $this->txFilterLine : null,
        );

        if ($full) {
            $data['committees'] = $this->event->committees()->get();
            $data['byCommittee'] = $service->byCommittee($lines);
            $data['breakEven'] = $service->breakEven($this->event, $lines);
            $data['versions'] = $service->versions($this->event);
            $data['latestVersion'] = $service->latestVersion($this->event);
            $data['pending'] = $service->pendingChanges($this->event);
        }

        return view('livewire.events.event-budget', $data);
    }

    // -------------------------------------------------------------- internals

    private function can(string $ability): bool
    {
        return Auth::user()->can($ability, [BudgetLine::class, $this->event]);
    }

    private function findLine(int $id): BudgetLine
    {
        return BudgetLine::query()->forEvent($this->event)->whereKey($id)->firstOrFail();
    }

    private function findTransaction(int $id): Transaction
    {
        return Transaction::query()
            ->whereKey($id)
            ->whereHas('line', fn ($q) => $q->where('event_id', $this->event->id))
            ->firstOrFail();
    }

    /** مبلغ ریالی ← متن ورودی بر اساس واحد فعلی (اگر تومانِ صحیح نشد، به ریال برمی‌گردد). */
    private function amountForInput(int $rial): string
    {
        if ($this->unit === Money::TOMAN) {
            if ($rial % 10 === 0) {
                return (string) intdiv($rial, 10);
            }

            $this->unit = Money::RIAL;
        }

        return (string) $rial;
    }

    private function clearMessages(): void
    {
        $this->error = null;
        $this->notice = null;
    }

    private function resetLineForm(): void
    {
        $this->reset([
            'showLineForm', 'editingLineId', 'lineName', 'lineAmount',
            'lineCommitteeId', 'lineAssumption',
        ]);
        $this->lineType = 'expense';
        $this->lineCategory = 'venue';
        $this->lineBehavior = 'fixed';
        $this->resetValidation();
    }

    private function resetTxForm(): void
    {
        $this->reset([
            'showTxForm', 'editingTxId', 'txLineId', 'txAmount', 'txDate',
            'txDescription', 'removeReceipt', 'receipt',
        ]);
        $this->resetValidation();
    }
}
