<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BudgetCategory;
use App\Enums\BudgetLineType;
use App\Enums\BudgetVersionStatus;
use App\Enums\CostBehavior;
use App\Enums\EventStatus;
use App\Models\BudgetLine;
use App\Models\BudgetVersion;
use App\Models\Committee;
use App\Models\Event;
use App\Models\Transaction;
use App\Models\User;
use App\Support\LogsActivity;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Step 09 — بودجه و درآمد.
 *
 * قواعد:
 *  - همه‌ی مبلغ‌ها عدد صحیح «ریال»‌اند (بدون float).
 *  - کاربر عادی (`budget_basic`): خطوط با دسته/نام/مبلغ + تراکنش + مجموع ساده.
 *  - کاربر سازمانی (`budget_full`): کمیته، ثابت/متغیر، فرض، رسید، نسخه و تأیید، سربه‌سر، هشدار قابل‌تنظیم.
 *  - نسخه: بدون ردیف نسخه = مقدماتی (شماره‌ی ۱)؛ آخرین نسخه «مصوب» = قفل (خطوط تغییر نمی‌کنند)؛
 *    «بازنگری» یک نسخه‌ی draft تازه می‌سازد. تراکنش‌های واقعی هیچ‌وقت قفل نمی‌شوند.
 *  - مجوز در Policy است؛ این سرویس فقط قواعد کسب‌وکار و سقف/ویژگی طرح را اعمال می‌کند.
 */
class BudgetService
{
    use LogsActivity;

    public const FEATURE_BASIC = 'budget_basic';

    public const FEATURE_FULL = 'budget_full';

    public function __construct(
        private EntitlementService $entitlement,
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

    // ---------------------------------------------------------------- versions

    public function latestVersion(Event $event): ?BudgetVersion
    {
        return BudgetVersion::query()
            ->where('event_id', $event->id)
            ->orderByDesc('number')
            ->first();
    }

    public function lastApproved(Event $event): ?BudgetVersion
    {
        return BudgetVersion::query()
            ->where('event_id', $event->id)
            ->where('status', BudgetVersionStatus::Approved->value)
            ->orderByDesc('number')
            ->first();
    }

    /** قفل = آخرین نسخه «مصوب» است و بازنگری باز نشده. */
    public function isLocked(Event $event): bool
    {
        $latest = $this->latestVersion($event);

        return $latest !== null && $latest->status === BudgetVersionStatus::Approved;
    }

    /** شماره‌ی نسخه‌ای که تغییرات فعلی خطوط به آن تعلق دارد. */
    public function workingNumber(Event $event): int
    {
        $latest = $this->latestVersion($event);

        if ($latest === null) {
            return 1;
        }

        return $latest->status === BudgetVersionStatus::Draft ? $latest->number : $latest->number + 1;
    }

    /** @return Collection<int, BudgetVersion> */
    public function versions(Event $event): Collection
    {
        return BudgetVersion::query()
            ->where('event_id', $event->id)
            ->with('approver')
            ->orderByDesc('number')
            ->get();
    }

    /**
     * تغییر خطوط جاری نسبت به آخرین نسخه‌ی مصوب (فقط وقتی بازنگری باز است).
     *
     * @return array{expense_delta: int, revenue_delta: int, balance_delta: int, base_number: int}|null
     */
    public function pendingChanges(Event $event): ?array
    {
        $latest = $this->latestVersion($event);
        $base = $this->lastApproved($event);

        if ($latest === null || $latest->status !== BudgetVersionStatus::Draft || $base === null) {
            return null;
        }

        $totals = $this->totals($event);
        $expenseDelta = $totals['planned_expense'] - (int) $base->total_expense;
        $revenueDelta = $totals['planned_revenue'] - (int) $base->total_revenue;

        return [
            'expense_delta' => $expenseDelta,
            'revenue_delta' => $revenueDelta,
            'balance_delta' => $revenueDelta - $expenseDelta,
            'base_number' => $base->number,
        ];
    }

    /**
     * تأیید بودجه توسط مدیر: snapshot ذخیره می‌شود و خطوط قفل می‌شوند.
     *
     * @throws InvalidArgumentException
     */
    public function approve(Event $event, User $actor, ?string $note = null): BudgetVersion
    {
        $this->assertWritable($event, $actor);
        $this->assertFull($actor);

        $note = $this->cleanText($note, 500);

        return DB::transaction(function () use ($event, $actor, $note) {
            $latest = BudgetVersion::query()
                ->where('event_id', $event->id)
                ->orderByDesc('number')
                ->lockForUpdate()
                ->first();

            if ($latest !== null && $latest->status === BudgetVersionStatus::Approved) {
                throw new InvalidArgumentException(__('budget.already_approved'));
            }

            $lines = BudgetLine::query()
                ->forEvent($event)
                ->with('committee')
                ->orderBy('type')
                ->orderBy('id')
                ->get();

            if ($lines->isEmpty()) {
                throw new InvalidArgumentException(__('budget.nothing_to_approve'));
            }

            $expense = (int) $lines->filter(fn (BudgetLine $l) => $l->isExpense())->sum('planned_amount');
            $revenue = (int) $lines->reject(fn (BudgetLine $l) => $l->isExpense())->sum('planned_amount');

            $snapshot = $lines->map(fn (BudgetLine $l): array => [
                'uuid' => $l->uuid,
                'type' => $l->type->value,
                'category' => $l->category->value,
                'name' => $l->name,
                'cost_behavior' => $l->cost_behavior->value,
                'planned_amount' => $l->planned_amount,
                'committee_id' => $l->committee_id,
                'committee_name' => $l->committee?->name,
                'assumption' => $l->assumption,
            ])->values()->all();

            BudgetVersion::query()
                ->where('event_id', $event->id)
                ->where('status', BudgetVersionStatus::Approved->value)
                ->update(['status' => BudgetVersionStatus::Superseded->value]);

            $version = $latest ?? new BudgetVersion(['event_id' => $event->id, 'number' => 1]);
            $version->fill([
                'status' => BudgetVersionStatus::Approved,
                'note' => $note,
                'snapshot' => $snapshot,
                'total_expense' => $expense,
                'total_revenue' => $revenue,
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ])->save();

            self::logActivity('budget.version_approved', $version, $event->id, [
                'number' => $version->number,
                'total_expense' => $expense,
                'total_revenue' => $revenue,
            ]);

            return $version;
        });
    }

    /**
     * شروع بازنگری روی بودجه‌ی مصوب (نسخه‌ی مقدماتی تازه؛ نسخه‌ی مصوب قبلی دست‌نخورده می‌ماند).
     *
     * @throws InvalidArgumentException
     */
    public function startRevision(Event $event, User $actor): BudgetVersion
    {
        $this->assertWritable($event, $actor);
        $this->assertFull($actor);

        return DB::transaction(function () use ($event) {
            $latest = BudgetVersion::query()
                ->where('event_id', $event->id)
                ->orderByDesc('number')
                ->lockForUpdate()
                ->first();

            if ($latest === null || $latest->status !== BudgetVersionStatus::Approved) {
                throw new InvalidArgumentException(__('budget.not_locked'));
            }

            $version = BudgetVersion::query()->create([
                'event_id' => $event->id,
                'number' => $latest->number + 1,
                'status' => BudgetVersionStatus::Draft,
            ]);

            self::logActivity('budget.revision_started', $version, $event->id, [
                'number' => $version->number,
            ]);

            return $version;
        });
    }

    // ------------------------------------------------------------------- lines

    /**
     * @param  array<string, mixed>  $data  type, category, name, planned_amount[, cost_behavior, committee_id, assumption]
     *
     * @throws InvalidArgumentException
     */
    public function addLine(Event $event, User $actor, array $data): BudgetLine
    {
        $this->assertWritable($event, $actor);
        $this->assertPlanEditable($event);

        $payload = $this->linePayload($event, $actor, $data);

        return DB::transaction(function () use ($event, $actor, $payload) {
            $line = BudgetLine::query()->create($payload + [
                'event_id' => $event->id,
                'created_by' => $actor->id,
                'version' => $this->workingNumber($event),
            ]);

            self::logActivity('budget.line_created', $line, $event->id, [
                'type' => $line->type->value,
                'category' => $line->category->value,
                'planned_amount' => $line->planned_amount,
            ]);

            return $line;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidArgumentException
     */
    public function updateLine(BudgetLine $line, User $actor, array $data): BudgetLine
    {
        $event = $line->event;

        $this->assertWritable($event, $actor);
        $this->assertPlanEditable($event);

        $current = [
            'type' => $line->type,
            'category' => $line->category,
            'name' => $line->name,
            'planned_amount' => $line->planned_amount,
            'cost_behavior' => $line->cost_behavior,
            'committee_id' => $line->committee_id,
            'assumption' => $line->assumption,
        ];

        $payload = $this->linePayload($event, $actor, array_merge($current, $data), updating: true);

        $newType = $payload['type'];
        if ($newType !== $line->type && $line->transactions()->exists()) {
            throw new InvalidArgumentException(__('budget.type_locked'));
        }

        return DB::transaction(function () use ($line, $event, $payload) {
            $line->update($payload + ['version' => $this->workingNumber($event)]);

            self::logActivity('budget.line_updated', $line, $event->id, [
                'changes' => array_keys($line->getChanges()),
            ]);

            return $line->fresh();
        });
    }

    /**
     * @throws InvalidArgumentException
     */
    public function deleteLine(BudgetLine $line, User $actor): void
    {
        $event = $line->event;

        $this->assertWritable($event, $actor);
        $this->assertPlanEditable($event);

        if ($line->transactions()->exists()) {
            throw new InvalidArgumentException(__('budget.line_has_transactions'));
        }

        DB::transaction(function () use ($line, $event) {
            self::logActivity('budget.line_deleted', $line, $event->id, [
                'name' => $line->name,
                'planned_amount' => $line->planned_amount,
            ]);

            $line->delete();
        });
    }

    // ------------------------------------------------------------ transactions

    /**
     * @param  array<string, mixed>  $data  amount (ریال)، date، description
     *
     * @throws InvalidArgumentException
     */
    public function recordTransaction(BudgetLine $line, User $actor, array $data, ?UploadedFile $receipt = null): Transaction
    {
        $event = $line->event;

        $this->assertWritable($event, $actor);

        if ($line->trashed()) {
            throw new InvalidArgumentException(__('budget.line_not_found'));
        }

        $amount = $this->amount($data['amount'] ?? null, allowZero: false);
        $date = $this->date($data['date'] ?? null);
        $description = $this->cleanText($data['description'] ?? null, 255);

        $path = $receipt !== null ? $this->storeReceipt($event, $actor, $receipt) : null;

        try {
            return DB::transaction(function () use ($line, $event, $actor, $amount, $date, $description, $path) {
                $transaction = Transaction::query()->create([
                    'budget_line_id' => $line->id,
                    'amount' => $amount,
                    'date' => $date->toDateString(),
                    'description' => $description,
                    'receipt_path' => $path,
                    'created_by' => $actor->id,
                ]);

                self::logActivity('budget.transaction_created', $transaction, $event->id, [
                    'budget_line_id' => $line->id,
                    'amount' => $amount,
                ]);

                return $transaction;
            });
        } catch (\Throwable $e) {
            if ($path !== null) {
                Storage::disk('local')->delete($path);
            }

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $data  amount، date، description (کلیدهای غایب دست نمی‌خورند)
     *
     * @throws InvalidArgumentException
     */
    public function updateTransaction(
        Transaction $transaction,
        User $actor,
        array $data,
        ?UploadedFile $receipt = null,
        bool $removeReceipt = false,
    ): Transaction {
        $line = $transaction->line;
        $event = $line->event;

        $this->assertWritable($event, $actor);

        $payload = [];
        if (array_key_exists('amount', $data)) {
            $payload['amount'] = $this->amount($data['amount'], allowZero: false);
        }
        if (array_key_exists('date', $data)) {
            $payload['date'] = $this->date($data['date'])->toDateString();
        }
        if (array_key_exists('description', $data)) {
            $payload['description'] = $this->cleanText($data['description'], 255);
        }

        $oldPath = $transaction->receipt_path;
        $newPath = null;

        if ($receipt !== null) {
            $newPath = $this->storeReceipt($event, $actor, $receipt);
            $payload['receipt_path'] = $newPath;
        } elseif ($removeReceipt) {
            $payload['receipt_path'] = null;
        }

        try {
            DB::transaction(function () use ($transaction, $event, $payload) {
                $transaction->update($payload);

                self::logActivity('budget.transaction_updated', $transaction, $event->id, [
                    'changes' => array_keys($transaction->getChanges()),
                ]);
            });
        } catch (\Throwable $e) {
            if ($newPath !== null) {
                Storage::disk('local')->delete($newPath);
            }

            throw $e;
        }

        if ($oldPath !== null && array_key_exists('receipt_path', $payload) && $payload['receipt_path'] !== $oldPath) {
            Storage::disk('local')->delete($oldPath);
        }

        return $transaction->fresh();
    }

    /**
     * حذف نرم؛ فایل رسید برای ممیزی نگه داشته می‌شود.
     *
     * @throws InvalidArgumentException
     */
    public function deleteTransaction(Transaction $transaction, User $actor): void
    {
        $line = $transaction->line;
        $event = $line->event;

        $this->assertWritable($event, $actor);

        DB::transaction(function () use ($transaction, $event) {
            self::logActivity('budget.transaction_deleted', $transaction, $event->id, [
                'budget_line_id' => $transaction->budget_line_id,
                'amount' => $transaction->amount,
            ]);

            $transaction->delete();
        });
    }

    /**
     * تراکنش‌های یک رویداد (جدیدترین اول)؛ فقط تراکنش‌های خطوط همین رویداد.
     *
     * @return Collection<int, Transaction>
     */
    public function transactions(Event $event, ?int $lineId = null, int $limit = 100): Collection
    {
        return Transaction::query()
            ->whereHas('line', fn ($q) => $q->where('event_id', $event->id))
            ->when($lineId !== null, fn ($q) => $q->where('budget_line_id', $lineId))
            ->with(['line', 'creator'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    // ------------------------------------------------------------- settings

    public function overrunThreshold(Event $event): int
    {
        $custom = data_get($event->metadata, 'budget.overrun_threshold_percent');

        if (is_int($custom) && $custom >= 0 && $custom <= 1000) {
            return $custom;
        }

        return (int) config('budget.overrun_threshold_percent', 10);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function setOverrunThreshold(Event $event, User $actor, int $percent): void
    {
        $this->assertWritable($event, $actor);
        $this->assertFull($actor);

        if ($percent < 0 || $percent > 1000) {
            throw new InvalidArgumentException(__('budget.threshold_invalid'));
        }

        $metadata = $event->metadata ?? [];
        data_set($metadata, 'budget.overrun_threshold_percent', $percent);
        $event->update(['metadata' => $metadata]);

        self::logActivity('budget.threshold_changed', $event, $event->id, ['percent' => $percent]);
    }

    // ------------------------------------------------------------- reporting

    /**
     * خطوط بودجه با جمع واقعی (`actual_amount`) و تعداد تراکنش (`transactions_count`).
     *
     * @return Collection<int, BudgetLine>
     */
    public function lines(Event $event): Collection
    {
        return BudgetLine::query()
            ->forEvent($event)
            ->with('committee')
            ->withSum('transactions as actual_amount', 'amount')
            ->withCount('transactions')
            ->orderBy('type')
            ->orderBy('category')
            ->orderBy('id')
            ->get();
    }

    /**
     * مجموع‌ها (برنامه در برابر واقعی). همه‌ی مبلغ‌ها ریال صحیح.
     *
     * @param  Collection<int, BudgetLine>|null  $lines
     * @return array<string, mixed>
     */
    public function totals(Event $event, ?Collection $lines = null): array
    {
        $lines ??= $this->lines($event);

        $plannedExpense = 0;
        $plannedRevenue = 0;
        $actualExpense = 0;
        $actualRevenue = 0;

        foreach ($lines as $line) {
            $actual = (int) $line->actual_amount;

            if ($line->isExpense()) {
                $plannedExpense += $line->planned_amount;
                $actualExpense += $actual;
            } else {
                $plannedRevenue += $line->planned_amount;
                $actualRevenue += $actual;
            }
        }

        $threshold = $this->overrunThreshold($event);

        return [
            'planned_expense' => $plannedExpense,
            'planned_revenue' => $plannedRevenue,
            'planned_balance' => $plannedRevenue - $plannedExpense,
            'actual_expense' => $actualExpense,
            'actual_revenue' => $actualRevenue,
            'actual_balance' => $actualRevenue - $actualExpense,
            'expense_usage_percent' => $this->percent($actualExpense, $plannedExpense),
            'revenue_achievement_percent' => $this->percent($actualRevenue, $plannedRevenue),
            'threshold' => $threshold,
            'expense_overrun' => $this->isOverrun($plannedExpense, $actualExpense, $threshold),
            'line_count' => $lines->count(),
        ];
    }

    /**
     * هشدار اضافه‌هزینه: فقط خطوط «هزینه» که واقعی از (برنامه + آستانه٪) بیشتر شده.
     *
     * @param  Collection<int, BudgetLine>  $lines
     * @return list<array{line: BudgetLine, planned: int, actual: int, excess: int, percent: float|null}>
     */
    public function overrunLines(Collection $lines, int $threshold): array
    {
        $alerts = [];

        foreach ($lines as $line) {
            if (! $line->isExpense()) {
                continue;
            }

            $actual = (int) $line->actual_amount;

            if (! $this->isOverrun($line->planned_amount, $actual, $threshold)) {
                continue;
            }

            $alerts[] = [
                'line' => $line,
                'planned' => $line->planned_amount,
                'actual' => $actual,
                'excess' => $actual - $line->planned_amount,
                'percent' => $line->planned_amount > 0
                    ? round(($actual - $line->planned_amount) * 100 / $line->planned_amount, 1)
                    : null,
            ];
        }

        return $alerts;
    }

    /**
     * جمع به تفکیک دسته (به ترتیب Enum).
     *
     * @param  Collection<int, BudgetLine>  $lines
     * @return list<array{category: BudgetCategory, type: BudgetLineType, planned: int, actual: int}>
     */
    public function byCategory(Collection $lines): array
    {
        $rows = [];

        foreach (BudgetCategory::cases() as $category) {
            $subset = $lines->filter(fn (BudgetLine $l) => $l->category === $category);

            if ($subset->isEmpty()) {
                continue;
            }

            $rows[] = [
                'category' => $category,
                'type' => $category->type(),
                'planned' => (int) $subset->sum('planned_amount'),
                'actual' => (int) $subset->sum(fn (BudgetLine $l) => (int) $l->actual_amount),
            ];
        }

        return $rows;
    }

    /**
     * هزینه به تفکیک کمیته (فقط خطوط هزینه؛ بدون کمیته = committee_id null).
     *
     * @param  Collection<int, BudgetLine>  $lines
     * @return list<array{committee_id: int|null, name: string|null, planned: int, actual: int}>
     */
    public function byCommittee(Collection $lines): array
    {
        $expenses = $lines->filter(fn (BudgetLine $l) => $l->isExpense());
        $rows = [];

        foreach ($expenses->groupBy(fn (BudgetLine $l) => $l->committee_id ?? 0) as $committeeId => $group) {
            /** @var BudgetLine $first */
            $first = $group->first();

            $rows[] = [
                'committee_id' => $committeeId === 0 ? null : (int) $committeeId,
                'name' => $first->committee?->name,
                'planned' => (int) $group->sum('planned_amount'),
                'actual' => (int) $group->sum(fn (BudgetLine $l) => (int) $l->actual_amount),
            ];
        }

        usort($rows, fn (array $a, array $b): int => $b['planned'] <=> $a['planned']);

        return $rows;
    }

    /**
     * نقطه‌ی سربه‌سر بر حسب «تعداد شرکت‌کننده» — فقط وقتی داده کافی است:
     *  - تعداد شرکت‌کننده‌ی مورد انتظار رویداد > ۰
     *  - حداقل یک خط هزینه و یک خط درآمد
     *  - درآمد «متغیر» (وابسته به شرکت‌کننده) > هزینه‌ی «متغیر»
     *
     * سربه‌سر = ceil(هزینه‌ی ثابت − درآمد ثابت) × N ÷ (درآمد متغیر − هزینه‌ی متغیر)
     * که N تعداد مورد انتظار است. این یک برآورد بر پایه‌ی فرض‌های ورودی است.
     *
     * @param  Collection<int, BudgetLine>  $lines
     * @return array{available: bool, reason: string|null, participants: int|null, expected: int|null, margin: int|null}
     */
    public function breakEven(Event $event, Collection $lines): array
    {
        $expected = (int) ($event->expected_participants ?? 0);

        $unavailable = fn (string $reason): array => [
            'available' => false,
            'reason' => $reason,
            'participants' => null,
            'expected' => $expected > 0 ? $expected : null,
            'margin' => null,
        ];

        if ($expected <= 0) {
            return $unavailable('no_participants');
        }

        $expenses = $lines->filter(fn (BudgetLine $l) => $l->isExpense());
        $revenues = $lines->reject(fn (BudgetLine $l) => $l->isExpense());

        if ($expenses->isEmpty() || $revenues->isEmpty()) {
            return $unavailable('no_lines');
        }

        $sum = fn (Collection $set, CostBehavior $behavior): int => (int) $set
            ->filter(fn (BudgetLine $l) => $l->cost_behavior === $behavior)
            ->sum('planned_amount');

        $fixedCost = $sum($expenses, CostBehavior::Fixed);
        $variableCost = $sum($expenses, CostBehavior::Variable);
        $fixedRevenue = $sum($revenues, CostBehavior::Fixed);
        $variableRevenue = $sum($revenues, CostBehavior::Variable);

        if ($variableRevenue <= 0) {
            return $unavailable('no_variable_revenue');
        }

        if ($variableRevenue <= $variableCost) {
            return $unavailable('no_margin');
        }

        $netFixed = $fixedCost - $fixedRevenue;

        $participants = $netFixed <= 0
            ? 0
            : (int) ceil(($netFixed * $expected) / ($variableRevenue - $variableCost));

        return [
            'available' => true,
            'reason' => null,
            'participants' => $participants,
            'expected' => $expected,
            'margin' => $expected - $participants,
        ];
    }

    // -------------------------------------------------------------- internals

    private function isOverrun(int $planned, int $actual, int $threshold): bool
    {
        $excess = $actual - $planned;

        return $excess > 0 && ($excess * 100) > ($planned * $threshold);
    }

    private function percent(int $part, int $whole): ?float
    {
        return $whole > 0 ? round($part * 100 / $whole, 1) : null;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function assertWritable(Event $event, User $actor): void
    {
        if (! $this->hasBasic($actor)) {
            throw new InvalidArgumentException(__('budget.feature_unavailable'));
        }

        if (in_array($event->status, [EventStatus::Archived, EventStatus::Cancelled], true)) {
            throw new InvalidArgumentException(__('budget.event_closed'));
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    private function assertFull(User $actor): void
    {
        if (! $this->hasFull($actor)) {
            throw new InvalidArgumentException(__('budget.full_only'));
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    private function assertPlanEditable(Event $event): void
    {
        if ($this->isLocked($event)) {
            throw new InvalidArgumentException(__('budget.locked'));
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    private function linePayload(Event $event, User $actor, array $data, bool $updating = false): array
    {
        $type = ($data['type'] ?? null) instanceof BudgetLineType
            ? $data['type']
            : BudgetLineType::tryFrom((string) ($data['type'] ?? ''));

        if ($type === null) {
            throw new InvalidArgumentException(__('budget.type_invalid'));
        }

        $category = ($data['category'] ?? null) instanceof BudgetCategory
            ? $data['category']
            : BudgetCategory::tryFrom((string) ($data['category'] ?? ''));

        if ($category === null || $category->type() !== $type) {
            throw new InvalidArgumentException(__('budget.category_invalid'));
        }

        $name = $this->cleanText($data['name'] ?? null, 160);
        if ($name === null) {
            throw new InvalidArgumentException(__('budget.name_required'));
        }

        $payload = [
            'type' => $type,
            'category' => $category,
            'name' => $name,
            'planned_amount' => $this->amount($data['planned_amount'] ?? null, allowZero: true),
        ];

        // امکانات «کامل»؛ در حساب عادی نادیده گرفته می‌شود (ایجاد: مقدار پیش‌فرض، ویرایش: بدون تغییر)
        if ($this->hasFull($actor)) {
            $behavior = ($data['cost_behavior'] ?? null) instanceof CostBehavior
                ? $data['cost_behavior']
                : CostBehavior::tryFrom((string) ($data['cost_behavior'] ?? ''));

            $payload['cost_behavior'] = $behavior ?? CostBehavior::Fixed;
            $payload['assumption'] = $this->cleanText($data['assumption'] ?? null, 1000);
            $payload['committee_id'] = $this->resolveCommittee($event, $data['committee_id'] ?? null);
        } elseif (! $updating) {
            $payload['cost_behavior'] = CostBehavior::Fixed;
            $payload['assumption'] = null;
            $payload['committee_id'] = null;
        }

        return $payload;
    }

    /**
     * کمیته باید متعلق به همین رویداد باشد (جلوگیری از نشت بین رویدادها/tenantها).
     *
     * @throws InvalidArgumentException
     */
    private function resolveCommittee(Event $event, mixed $committeeId): ?int
    {
        if ($committeeId === null || $committeeId === '' || $committeeId === 0 || $committeeId === '0') {
            return null;
        }

        $exists = Committee::query()
            ->where('event_id', $event->id)
            ->whereKey((int) $committeeId)
            ->exists();

        if (! $exists) {
            throw new InvalidArgumentException(__('budget.committee_invalid'));
        }

        return (int) $committeeId;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function amount(mixed $value, bool $allowZero): int
    {
        if (is_string($value) && preg_match('/^\d{1,16}$/', $value) === 1) {
            $value = (int) $value;
        }

        if (! is_int($value) || $value < 0 || (! $allowZero && $value === 0)) {
            throw new InvalidArgumentException(__('budget.amount_invalid'));
        }

        if ($value > Money::maxAmount()) {
            throw new InvalidArgumentException(__('budget.amount_too_large', [
                'max' => Money::format(Money::maxAmount()),
            ]));
        }

        return $value;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function date(mixed $value): CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException(__('budget.date_invalid'));
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            throw new InvalidArgumentException(__('budget.date_invalid'));
        }
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

        return mb_substr($text, 0, $max);
    }

    /**
     * @throws InvalidArgumentException
     */
    private function storeReceipt(Event $event, User $actor, UploadedFile $file): string
    {
        if (! $this->hasFull($actor)) {
            throw new InvalidArgumentException(__('budget.full_only'));
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $allowed = (array) config('budget.receipt_mimes', ['pdf', 'jpg', 'jpeg', 'png']);
        $maxBytes = (int) config('budget.receipt_max_kb', 5120) * 1024;

        if (! in_array($extension, $allowed, true) || (int) $file->getSize() > $maxBytes) {
            throw new InvalidArgumentException(__('budget.receipt_invalid', [
                'types' => strtoupper(implode('، ', $allowed)),
                'max' => (int) config('budget.receipt_max_kb', 5120) / 1024,
            ]));
        }

        $path = $file->store('budget-receipts/'.$event->id, 'local');

        if (! is_string($path)) {
            throw new InvalidArgumentException(__('budget.receipt_store_failed'));
        }

        return $path;
    }
}
