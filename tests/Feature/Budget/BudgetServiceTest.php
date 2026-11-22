<?php

use App\Enums\CostBehavior;
use App\Enums\EventType;
use App\Models\ActivityLog;
use App\Models\BudgetLine;
use App\Models\BudgetVersion;
use App\Models\Event;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BudgetService;
use App\Services\CommitteeService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function bud_event(User $owner, array $attrs = []): Event
{
    return Event::query()->create(array_merge([
        'created_by' => $owner->id,
        'title' => 'رویداد بودجه '.fake()->unique()->numerify('####'),
        'event_type' => EventType::Local,
        'scale' => 'small',
        'status' => 'draft',
    ], $attrs));
}

function bud_line(BudgetService $svc, Event $event, User $actor, array $data = []): BudgetLine
{
    return $svc->addLine($event, $actor, array_merge([
        'type' => 'expense',
        'category' => 'venue',
        'name' => 'اجاره سالن',
        'planned_amount' => 1_000_000,
    ], $data));
}

function bud_tx(BudgetService $svc, BudgetLine $line, User $actor, int $amount, array $data = []): Transaction
{
    return $svc->recordTransaction($line, $actor, array_merge([
        'amount' => $amount,
        'date' => '2026-11-20',
    ], $data));
}

// ------------------------------------------------------------- math

it('keeps totals exact in integer rial', function () {
    $owner = User::factory()->organizational()->create();
    $event = bud_event($owner);
    $svc = app(BudgetService::class);

    $venue = bud_line($svc, $event, $owner, ['planned_amount' => 9_999_999_999_999]);
    $tickets = bud_line($svc, $event, $owner, [
        'type' => 'revenue', 'category' => 'tickets', 'name' => 'ثبت‌نام', 'planned_amount' => 5_000_000,
    ]);

    bud_tx($svc, $venue, $owner, 1);
    bud_tx($svc, $venue, $owner, 9_999_999_999_998);
    bud_tx($svc, $tickets, $owner, 3_000_000);

    $t = $svc->totals($event);

    expect($t['planned_expense'])->toBe(9_999_999_999_999)
        ->and($t['actual_expense'])->toBe(9_999_999_999_999)
        ->and($t['planned_revenue'])->toBe(5_000_000)
        ->and($t['actual_revenue'])->toBe(3_000_000)
        ->and($t['planned_balance'])->toBe(5_000_000 - 9_999_999_999_999)
        ->and($t['actual_balance'])->toBe(3_000_000 - 9_999_999_999_999)
        ->and($t['expense_usage_percent'])->toBe(100.0)
        ->and($t['revenue_achievement_percent'])->toBe(60.0)
        ->and($t['line_count'])->toBe(2);
});

it('returns null percentages when nothing is planned', function () {
    $owner = User::factory()->organizational()->create();
    $event = bud_event($owner);

    $t = app(BudgetService::class)->totals($event);

    expect($t['planned_expense'])->toBe(0)
        ->and($t['expense_usage_percent'])->toBeNull()
        ->and($t['revenue_achievement_percent'])->toBeNull()
        ->and($t['expense_overrun'])->toBeFalse();
});

it('rejects invalid money values', function (mixed $amount) {
    $owner = User::factory()->organizational()->create();
    $event = bud_event($owner);
    $svc = app(BudgetService::class);

    expect(fn () => bud_line($svc, $event, $owner, ['planned_amount' => $amount]))
        ->toThrow(InvalidArgumentException::class);
})->with([-5, 1.5, 'abc', 10_000_000_000_001]);

it('allows a zero planned amount but never a zero transaction', function () {
    $owner = User::factory()->organizational()->create();
    $event = bud_event($owner);
    $svc = app(BudgetService::class);

    $line = bud_line($svc, $event, $owner, ['planned_amount' => 0]);

    expect($line->planned_amount)->toBe(0)
        ->and(fn () => bud_tx($svc, $line, $owner, 0))->toThrow(InvalidArgumentException::class, __('budget.amount_invalid'));
});

it('rejects invalid transaction dates', function () {
    $owner = User::factory()->organizational()->create();
    $event = bud_event($owner);
    $svc = app(BudgetService::class);
    $line = bud_line($svc, $event, $owner);

    expect(fn () => $svc->recordTransaction($line, $owner, ['amount' => 10, 'date' => 'not a date']))
        ->toThrow(InvalidArgumentException::class, __('budget.date_invalid'))
        ->and(fn () => $svc->recordTransaction($line, $owner, ['amount' => 10]))
        ->toThrow(InvalidArgumentException::class, __('budget.date_invalid'));
});

// ------------------------------------------------------ line validation

it('requires the category to match the line type', function () {
    $owner = User::factory()->organizational()->create();
    $event = bud_event($owner);
    $svc = app(BudgetService::class);

    expect(fn () => bud_line($svc, $event, $owner, ['type' => 'expense', 'category' => 'tickets']))
        ->toThrow(InvalidArgumentException::class, __('budget.category_invalid'))
        ->and(fn () => bud_line($svc, $event, $owner, ['type' => 'revenue', 'category' => 'venue']))
        ->toThrow(InvalidArgumentException::class, __('budget.category_invalid'))
        ->and(fn () => bud_line($svc, $event, $owner, ['type' => 'other']))
        ->toThrow(InvalidArgumentException::class, __('budget.type_invalid'))
        ->and(fn () => bud_line($svc, $event, $owner, ['name' => '   ']))
        ->toThrow(InvalidArgumentException::class, __('budget.name_required'));
});

it('rejects a committee that belongs to another event', function () {
    $owner = User::factory()->organizational()->create();
    $eventA = bud_event($owner);
    $eventB = bud_event($owner);
    $foreign = app(CommitteeService::class)->create($eventB, $owner, ['name' => 'کمیته B']);
    $svc = app(BudgetService::class);

    expect(fn () => bud_line($svc, $eventA, $owner, ['committee_id' => $foreign->id]))
        ->toThrow(InvalidArgumentException::class, __('budget.committee_invalid'));

    $own = app(CommitteeService::class)->create($eventA, $owner, ['name' => 'کمیته A']);
    $line = bud_line($svc, $eventA, $owner, ['committee_id' => $own->id, 'cost_behavior' => 'variable', 'assumption' => 'فرض']);

    expect($line->committee_id)->toBe($own->id)
        ->and($line->cost_behavior)->toBe(CostBehavior::Variable)
        ->and($line->assumption)->toBe('فرض');
});

it('does not let a line with transactions change its type or be deleted', function () {
    $owner = User::factory()->organizational()->create();
    $event = bud_event($owner);
    $svc = app(BudgetService::class);
    $line = bud_line($svc, $event, $owner);
    bud_tx($svc, $line, $owner, 100);

    expect(fn () => $svc->updateLine($line, $owner, ['type' => 'revenue', 'category' => 'tickets']))
        ->toThrow(InvalidArgumentException::class, __('budget.type_locked'))
        ->and(fn () => $svc->deleteLine($line, $owner))
        ->toThrow(InvalidArgumentException::class, __('budget.line_has_transactions'));
});

it('soft deletes lines and transactions and ignores them in totals', function () {
    $owner = User::factory()->organizational()->create();
    $event = bud_event($owner);
    $svc = app(BudgetService::class);

    $line = bud_line($svc, $event, $owner);
    $other = bud_line($svc, $event, $owner, ['name' => 'تجهیزات', 'category' => 'equipment', 'planned_amount' => 500]);
    $tx = bud_tx($svc, $other, $owner, 300);

    $svc->deleteLine($line, $owner);
    expect(BudgetLine::withTrashed()->count())->toBe(2)
        ->and($svc->totals($event)['planned_expense'])->toBe(500);

    $svc->deleteTransaction($tx, $owner);
    expect(Transaction::withTrashed()->count())->toBe(1)
        ->and($svc->totals($event)['actual_expense'])->toBe(0);
});

// -------------------------------------------------------- plan limits

it('gives the basic plan simple lines only and blocks full features', function () {
    $user = User::factory()->individual()->create();
    $event = bud_event($user);
    $committee = app(CommitteeService::class)->create($event, $user, ['name' => 'مالی']);
    $svc = app(BudgetService::class);

    $line = bud_line($svc, $event, $user, [
        'cost_behavior' => 'variable',
        'committee_id' => $committee->id,
        'assumption' => 'نباید ذخیره شود',
    ]);

    expect($line->cost_behavior)->toBe(CostBehavior::Fixed)
        ->and($line->committee_id)->toBeNull()
        ->and($line->assumption)->toBeNull();

    // مجموع ساده و تراکنش آزاد است
    bud_tx($svc, $line, $user, 400_000);
    expect($svc->totals($event)['actual_expense'])->toBe(400_000);

    // ویرایش در حساب عادی فیلدهای کامل را دست نمی‌زند
    $updated = $svc->updateLine($line, $user, ['name' => 'سالن جدید', 'planned_amount' => 2_000_000]);
    expect($updated->name)->toBe('سالن جدید')->and($updated->planned_amount)->toBe(2_000_000);

    $full = __('budget.full_only');
    expect(fn () => $svc->approve($event, $user))->toThrow(InvalidArgumentException::class, $full)
        ->and(fn () => $svc->startRevision($event, $user))->toThrow(InvalidArgumentException::class, $full)
        ->and(fn () => $svc->setOverrunThreshold($event, $user, 20))->toThrow(InvalidArgumentException::class, $full)
        ->and(fn () => $svc->recordTransaction($line, $user, ['amount' => 10, 'date' => '2026-11-20'], UploadedFile::fake()->create('r.pdf', 10, 'application/pdf')))
        ->toThrow(InvalidArgumentException::class, $full);
});

it('blocks users without a budget feature', function () {
    $user = User::factory()->withoutAccountType()->create();
    $event = bud_event($user);

    expect(fn () => bud_line(app(BudgetService::class), $event, $user))
        ->toThrow(InvalidArgumentException::class, __('budget.feature_unavailable'));
});

it('makes closed events read-only', function () {
    $owner = User::factory()->organizational()->create();
    $event = bud_event($owner);
    $svc = app(BudgetService::class);
    $line = bud_line($svc, $event, $owner);

    $event->update(['status' => 'archived']);

    expect(fn () => bud_line($svc, $event->fresh(), $owner))->toThrow(InvalidArgumentException::class, __('budget.event_closed'))
        ->and(fn () => bud_tx($svc, $line->fresh(), $owner, 10))->toThrow(InvalidArgumentException::class, __('budget.event_closed'));

    $event->update(['status' => 'cancelled']);
    expect(fn () => bud_tx($svc, $line->fresh(), $owner, 10))->toThrow(InvalidArgumentException::class, __('budget.event_closed'));

    // رویداد تکمیل‌شده هنوز می‌تواند تراکنش بگیرد
    $event->update(['status' => 'completed']);
    expect(bud_tx($svc, $line->fresh(), $owner, 10)->amount)->toBe(10);
});

// ----------------------------------------------------------- versions

it('locks the plan on approval and keeps snapshots immutable across revisions', function () {
    $owner = User::factory()->organizational()->create();
    $event = bud_event($owner);
    $svc = app(BudgetService::class);

    $line = bud_line($svc, $event, $owner, ['planned_amount' => 1_000_000]);
    bud_line($svc, $event, $owner, ['type' => 'revenue', 'category' => 'sponsorship', 'name' => 'اسپانسر', 'planned_amount' => 3_000_000]);

    expect($svc->isLocked($event))->toBeFalse()
        ->and($svc->workingNumber($event))->toBe(1);

    $v1 = $svc->approve($event, $owner, 'نسخه‌ی اول');

    expect($v1->number)->toBe(1)
        ->and($v1->isApproved())->toBeTrue()
        ->and($v1->total_expense)->toBe(1_000_000)
        ->and($v1->total_revenue)->toBe(3_000_000)
        ->and($v1->balance())->toBe(2_000_000)
        ->and($v1->approved_by)->toBe($owner->id)
        ->and($v1->snapshot)->toHaveCount(2)
        ->and($svc->isLocked($event))->toBeTrue();

    $locked = __('budget.locked');
    expect(fn () => $svc->updateLine($line, $owner, ['planned_amount' => 9]))->toThrow(InvalidArgumentException::class, $locked)
        ->and(fn () => bud_line($svc, $event, $owner))->toThrow(InvalidArgumentException::class, $locked)
        ->and(fn () => $svc->deleteLine($line, $owner))->toThrow(InvalidArgumentException::class, $locked)
        ->and(fn () => $svc->approve($event, $owner))->toThrow(InvalidArgumentException::class, __('budget.already_approved'));

    // تراکنش واقعی قفل نمی‌شود
    expect(bud_tx($svc, $line, $owner, 700_000)->amount)->toBe(700_000);

    $rev = $svc->startRevision($event, $owner);
    expect($rev->number)->toBe(2)
        ->and($svc->isLocked($event))->toBeFalse()
        ->and(fn () => $svc->startRevision($event, $owner))->toThrow(InvalidArgumentException::class, __('budget.not_locked'));

    $svc->updateLine($line, $owner, ['planned_amount' => 2_500_000]);
    expect($line->fresh()->version)->toBe(2);

    // نسخه‌ی مصوب قبلی تغییر نکرده
    $v1Fresh = BudgetVersion::query()->where('event_id', $event->id)->where('number', 1)->firstOrFail();
    expect($v1Fresh->snapshot[0]['planned_amount'])->toBe(1_000_000)
        ->and($v1Fresh->total_expense)->toBe(1_000_000);

    $pending = $svc->pendingChanges($event);
    expect($pending['expense_delta'])->toBe(1_500_000)
        ->and($pending['revenue_delta'])->toBe(0)
        ->and($pending['balance_delta'])->toBe(-1_500_000)
        ->and($pending['base_number'])->toBe(1);

    $v2 = $svc->approve($event, $owner);
    expect($v2->number)->toBe(2)
        ->and($v2->total_expense)->toBe(2_500_000)
        ->and($v1Fresh->fresh()->status->value)->toBe('superseded')
        ->and($svc->isLocked($event))->toBeTrue()
        ->and($svc->versions($event)->pluck('number')->all())->toBe([2, 1])
        ->and($svc->pendingChanges($event))->toBeNull();
});

it('refuses to approve an empty budget', function () {
    $owner = User::factory()->organizational()->create();
    $event = bud_event($owner);

    expect(fn () => app(BudgetService::class)->approve($event, $owner))
        ->toThrow(InvalidArgumentException::class, __('budget.nothing_to_approve'));
});

// -------------------------------------------------------------- overrun

it('alerts only when actual exceeds planned by more than the threshold', function () {
    $owner = User::factory()->organizational()->create();
    $event = bud_event($owner);
    $svc = app(BudgetService::class);
    $line = bud_line($svc, $event, $owner, ['planned_amount' => 1_000_000]);

    bud_tx($svc, $line, $owner, 1_100_000); // دقیقاً ۱۰٪ بالاتر: هشدار نه
    $lines = $svc->lines($event);
    expect($svc->overrunLines($lines, 10))->toBe([])
        ->and($svc->totals($event)['expense_overrun'])->toBeFalse();

    bud_tx($svc, $line, $owner, 1); // یک ریال بیشتر: هشدار
    $lines = $svc->lines($event);
    $alerts = $svc->overrunLines($lines, 10);

    expect($alerts)->toHaveCount(1)
        ->and($alerts[0]['excess'])->toBe(100_001)
        ->and($alerts[0]['percent'])->toBe(10.0)
        ->and($svc->totals($event)['expense_overrun'])->toBeTrue();

    // آستانه‌ی بالاتر هشدار را برمی‌دارد
    expect($svc->overrunLines($lines, 15))->toBe([]);
});

it('uses the per-event threshold override on organizational accounts', function () {
    $owner = User::factory()->organizational()->create();
    $event = bud_event($owner);
    $svc = app(BudgetService::class);

    expect($svc->overrunThreshold($event))->toBe(10);

    $svc->setOverrunThreshold($event, $owner, 25);

    expect($svc->overrunThreshold($event))->toBe(25)
        ->and($svc->overrunThreshold($event->fresh()))->toBe(25)
        ->and(fn () => $svc->setOverrunThreshold($event, $owner, 1001))
        ->toThrow(InvalidArgumentException::class, __('budget.threshold_invalid'));

    $line = bud_line($svc, $event, $owner, ['planned_amount' => 1000]);
    bud_tx($svc, $line, $owner, 1200); // ۲۰٪ < ۲۵٪
    expect($svc->totals($event)['expense_overrun'])->toBeFalse();
});

it('flags spending on an unplanned line and never alerts on revenue', function () {
    $owner = User::factory()->organizational()->create();
    $event = bud_event($owner);
    $svc = app(BudgetService::class);

    $free = bud_line($svc, $event, $owner, ['planned_amount' => 0, 'name' => 'متفرقه', 'category' => 'contingency']);
    $income = bud_line($svc, $event, $owner, ['type' => 'revenue', 'category' => 'tickets', 'name' => 'بلیت', 'planned_amount' => 100]);
    bud_tx($svc, $free, $owner, 5);
    bud_tx($svc, $income, $owner, 100_000);

    $alerts = $svc->overrunLines($svc->lines($event), 10);

    expect($alerts)->toHaveCount(1)
        ->and($alerts[0]['line']->id)->toBe($free->id)
        ->and($alerts[0]['percent'])->toBeNull();
});

// ---------------------------------------------------------- breakdowns

it('groups by category and by committee', function () {
    $owner = User::factory()->organizational()->create();
    $event = bud_event($owner);
    $svc = app(BudgetService::class);
    $committee = app(CommitteeService::class)->create($event, $owner, ['name' => 'فنی']);

    $a = bud_line($svc, $event, $owner, ['planned_amount' => 100, 'committee_id' => $committee->id]);
    $b = bud_line($svc, $event, $owner, ['name' => 'سالن دوم', 'planned_amount' => 50]);
    $c = bud_line($svc, $event, $owner, ['name' => 'تجهیزات', 'category' => 'equipment', 'planned_amount' => 70, 'committee_id' => $committee->id]);
    bud_tx($svc, $a, $owner, 30);
    bud_tx($svc, $c, $owner, 10);

    $lines = $svc->lines($event);
    $byCategory = collect($svc->byCategory($lines))->keyBy(fn ($r) => $r['category']->value);
    $byCommittee = collect($svc->byCommittee($lines))->keyBy(fn ($r) => $r['committee_id'] ?? 0);

    expect($byCategory['venue']['planned'])->toBe(150)
        ->and($byCategory['venue']['actual'])->toBe(30)
        ->and($byCategory['equipment']['planned'])->toBe(70)
        ->and($byCommittee[$committee->id]['planned'])->toBe(170)
        ->and($byCommittee[$committee->id]['actual'])->toBe(40)
        ->and($byCommittee[0]['planned'])->toBe(50)
        ->and($byCommittee[0]['name'])->toBeNull()
        ->and($b->id)->toBeInt();
});

// ----------------------------------------------------------- break-even

it('reports insufficient data instead of guessing the break-even', function () {
    $owner = User::factory()->organizational()->create();
    $svc = app(BudgetService::class);

    $noParticipants = bud_event($owner);
    bud_line($svc, $noParticipants, $owner);
    expect($svc->breakEven($noParticipants, $svc->lines($noParticipants)))
        ->toMatchArray(['available' => false, 'reason' => 'no_participants', 'participants' => null]);

    $noRevenue = bud_event($owner, ['expected_participants' => 100]);
    bud_line($svc, $noRevenue, $owner);
    expect($svc->breakEven($noRevenue, $svc->lines($noRevenue)))
        ->toMatchArray(['available' => false, 'reason' => 'no_lines']);

    $noVariable = bud_event($owner, ['expected_participants' => 100]);
    bud_line($svc, $noVariable, $owner);
    bud_line($svc, $noVariable, $owner, ['type' => 'revenue', 'category' => 'sponsorship', 'name' => 'اسپانسر', 'planned_amount' => 5_000_000]);
    expect($svc->breakEven($noVariable, $svc->lines($noVariable)))
        ->toMatchArray(['available' => false, 'reason' => 'no_variable_revenue']);

    $noMargin = bud_event($owner, ['expected_participants' => 100]);
    bud_line($svc, $noMargin, $owner, ['cost_behavior' => 'variable', 'planned_amount' => 9_000_000]);
    bud_line($svc, $noMargin, $owner, ['type' => 'revenue', 'category' => 'tickets', 'name' => 'ثبت‌نام', 'cost_behavior' => 'variable', 'planned_amount' => 9_000_000]);
    expect($svc->breakEven($noMargin, $svc->lines($noMargin)))
        ->toMatchArray(['available' => false, 'reason' => 'no_margin']);
});

it('computes the break-even participants when data is sufficient', function () {
    $owner = User::factory()->organizational()->create();
    $event = bud_event($owner, ['expected_participants' => 100]);
    $svc = app(BudgetService::class);

    bud_line($svc, $event, $owner, ['planned_amount' => 50_000_000]); // هزینه‌ی ثابت
    bud_line($svc, $event, $owner, ['name' => 'پذیرایی', 'category' => 'staff', 'cost_behavior' => 'variable', 'planned_amount' => 20_000_000]);
    bud_line($svc, $event, $owner, ['type' => 'revenue', 'category' => 'tickets', 'name' => 'ثبت‌نام', 'cost_behavior' => 'variable', 'planned_amount' => 100_000_000]);
    bud_line($svc, $event, $owner, ['type' => 'revenue', 'category' => 'sponsorship', 'name' => 'اسپانسر', 'planned_amount' => 10_000_000]);

    // خالص ثابت = ۵۰M − ۱۰M = ۴۰M ؛ سهم هر ۱۰۰ نفر = ۱۰۰M − ۲۰M = ۸۰M ⇒ ۵۰ نفر
    expect($svc->breakEven($event, $svc->lines($event)))->toBe([
        'available' => true,
        'reason' => null,
        'participants' => 50,
        'expected' => 100,
        'margin' => 50,
    ]);
});

it('treats a fully covered fixed cost as zero break-even participants', function () {
    $owner = User::factory()->organizational()->create();
    $event = bud_event($owner, ['expected_participants' => 10]);
    $svc = app(BudgetService::class);

    bud_line($svc, $event, $owner, ['planned_amount' => 1_000]);
    bud_line($svc, $event, $owner, ['type' => 'revenue', 'category' => 'sponsorship', 'name' => 'اسپانسر', 'planned_amount' => 5_000]);
    bud_line($svc, $event, $owner, ['type' => 'revenue', 'category' => 'tickets', 'name' => 'بلیت', 'cost_behavior' => 'variable', 'planned_amount' => 800]);

    expect($svc->breakEven($event, $svc->lines($event))['participants'])->toBe(0);
});

// --------------------------------------------------------------- receipts

it('stores receipts on the private disk and cleans replaced files', function () {
    Storage::fake('local');

    $owner = User::factory()->organizational()->create();
    $event = bud_event($owner);
    $svc = app(BudgetService::class);
    $line = bud_line($svc, $event, $owner);

    $tx = $svc->recordTransaction($line, $owner, ['amount' => 500, 'date' => '2026-11-20'], UploadedFile::fake()->create('r1.pdf', 20, 'application/pdf'));
    $first = $tx->receipt_path;

    expect($first)->toStartWith('budget-receipts/'.$event->id.'/');
    Storage::disk('local')->assertExists($first);

    $tx = $svc->updateTransaction($tx, $owner, [], UploadedFile::fake()->create('r2.png', 20, 'image/png'));
    $second = $tx->receipt_path;

    Storage::disk('local')->assertMissing($first);
    Storage::disk('local')->assertExists($second);

    $tx = $svc->updateTransaction($tx, $owner, [], null, true);

    expect($tx->receipt_path)->toBeNull();
    Storage::disk('local')->assertMissing($second);
});

it('rejects receipts with a bad extension or size', function () {
    Storage::fake('local');

    $owner = User::factory()->organizational()->create();
    $event = bud_event($owner);
    $svc = app(BudgetService::class);
    $line = bud_line($svc, $event, $owner);
    $data = ['amount' => 10, 'date' => '2026-11-20'];

    expect(fn () => $svc->recordTransaction($line, $owner, $data, UploadedFile::fake()->create('virus.exe', 10)))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $svc->recordTransaction($line, $owner, $data, UploadedFile::fake()->create('big.pdf', 6000, 'application/pdf')))
        ->toThrow(InvalidArgumentException::class)
        ->and(Transaction::query()->count())->toBe(0);
});

// ------------------------------------------------------------ isolation

it('lists only the transactions of the requested event', function () {
    $owner = User::factory()->organizational()->create();
    $svc = app(BudgetService::class);

    $eventA = bud_event($owner);
    $eventB = bud_event($owner);
    $lineA = bud_line($svc, $eventA, $owner);
    $lineB = bud_line($svc, $eventB, $owner);
    bud_tx($svc, $lineA, $owner, 11);
    bud_tx($svc, $lineB, $owner, 22);

    expect($svc->transactions($eventA)->pluck('amount')->all())->toBe([11])
        ->and($svc->lines($eventA)->pluck('id')->all())->toBe([$lineA->id])
        ->and($svc->totals($eventB)['actual_expense'])->toBe(22);
});

// ------------------------------------------------------------ audit log

it('writes activity log entries for budget changes', function () {
    $owner = User::factory()->organizational()->create();
    $event = bud_event($owner);
    $svc = app(BudgetService::class);

    $line = bud_line($svc, $event, $owner);
    $svc->updateLine($line, $owner, ['planned_amount' => 2_000_000]);
    $tx = bud_tx($svc, $line, $owner, 10);
    $svc->updateTransaction($tx, $owner, ['amount' => 20]);
    $svc->deleteTransaction($tx->fresh(), $owner);
    $svc->approve($event, $owner);
    $svc->startRevision($event, $owner);

    $actions = ActivityLog::query()->where('event_id', $event->id)->pluck('action')->all();

    expect($actions)->toContain(
        'budget.line_created',
        'budget.line_updated',
        'budget.transaction_created',
        'budget.transaction_updated',
        'budget.transaction_deleted',
        'budget.version_approved',
        'budget.revision_started',
    );
});
