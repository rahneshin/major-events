<?php

use App\Enums\BudgetCategory;
use App\Enums\EventRole;
use App\Livewire\Events\EventBudget;
use App\Models\BudgetLine;
use App\Models\Event;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BudgetService;
use App\Services\CommitteeService;
use App\Services\EventService;
use App\Services\OrganizationService;
use App\Support\Jalali;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function acc_event(User $owner, array $data = []): Event
{
    return app(EventService::class)->create($owner, array_merge(['title' => 'رویداد دسترسی بودجه'], $data));
}

function acc_line(Event $event, User $actor, array $data = []): BudgetLine
{
    return app(BudgetService::class)->addLine($event, $actor, array_merge([
        'type' => 'expense',
        'category' => 'venue',
        'name' => 'اجاره سالن',
        'planned_amount' => 1_000_000,
    ], $data));
}

function acc_join(Event $event, User $user, EventRole $role): void
{
    $event->members()->attach($user->id, [
        'role' => $role->value,
        'added_by' => $event->created_by,
        'joined_at' => now(),
    ]);
}

/** Livewire may throw or answer 404 for a foreign id; both mean "blocked". */
function acc_blocked(callable $action): void
{
    try {
        $action();
    } catch (ModelNotFoundException|Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
        // expected
    }

    expect(true)->toBeTrue();
}

// ----------------------------------------------------------- policy matrix

it('applies the budget access matrix', function () {
    $owner = User::factory()->organizational()->create();
    $org = app(OrganizationService::class)->create($owner, ['name' => 'سازمان بودجه', 'type' => 'club']);
    $event = acc_event($owner, ['organization_id' => $org->id]);

    $manager = User::factory()->organizational()->create();
    $head = User::factory()->organizational()->create();
    $member = User::factory()->organizational()->create();
    $viewer = User::factory()->organizational()->create();
    $orgManager = User::factory()->organizational()->create();
    $orgPlain = User::factory()->organizational()->create();
    $stranger = User::factory()->organizational()->create();

    acc_join($event, $manager, EventRole::EventManager);
    acc_join($event, $member, EventRole::Member);
    acc_join($event, $viewer, EventRole::Viewer);
    app(CommitteeService::class)->create($event, $owner, ['name' => 'مالی', 'head_user_id' => $head->id]);
    $org->members()->attach($orgManager->id, ['role' => 'manager', 'joined_at' => now()]);
    $org->members()->attach($orgPlain->id, ['role' => 'member', 'joined_at' => now()]);

    $matrix = [
        'owner' => [$owner, true, true],
        'event_manager' => [$manager, true, true],
        'committee_head' => [$head, false, true],
        'member' => [$member, false, true],
        'viewer' => [$viewer, false, false],
        'org manager' => [$orgManager, true, true],
        'org plain member' => [$orgPlain, false, false],
        'stranger' => [$stranger, false, false],
    ];

    foreach ($matrix as $who => [$user, $manage, $summary]) {
        expect($user->can('manage', [BudgetLine::class, $event]))->toBe($manage, "$who manage")
            ->and($user->can('approve', [BudgetLine::class, $event]))->toBe($manage, "$who approve")
            ->and($user->can('viewSummary', [BudgetLine::class, $event]))->toBe($summary, "$who viewSummary");
    }
});

// -------------------------------------------------------------- page

it('shows the budget tab to members and hides it from strangers', function () {
    $this->withoutVite();

    $owner = User::factory()->organizational()->create();
    $stranger = User::factory()->organizational()->create();
    $event = acc_event($owner);

    $this->actingAs($owner)
        ->get(route('events.show', ['event' => $event, 'tab' => 'budget']))
        ->assertOk()
        ->assertSeeLivewire(EventBudget::class);

    $this->actingAs($stranger)
        ->get(route('events.show', ['event' => $event, 'tab' => 'budget']))
        ->assertForbidden();
});

// ---------------------------------------------------------- Livewire access

it('forbids strangers from loading the budget component', function () {
    $owner = User::factory()->organizational()->create();
    $stranger = User::factory()->organizational()->create();
    $event = acc_event($owner);

    Livewire::actingAs($stranger)
        ->test(EventBudget::class, ['event' => $event])
        ->assertForbidden();
});

it('shows viewers no budget data and blocks every action', function () {
    $owner = User::factory()->organizational()->create();
    $viewer = User::factory()->organizational()->create();
    $event = acc_event($owner);
    acc_join($event, $viewer, EventRole::Viewer);
    acc_line($event, $owner, ['name' => 'ردیف محرمانه']);

    Livewire::actingAs($viewer)
        ->test(EventBudget::class, ['event' => $event])
        ->assertSee(__('budget.no_access_title'))
        ->assertDontSee('ردیف محرمانه')
        ->call('openLineForm')
        ->assertForbidden();

    Livewire::actingAs($viewer)
        ->test(EventBudget::class, ['event' => $event])
        ->call('saveLine')
        ->assertForbidden();
});

it('shows members totals only and blocks edits', function () {
    $owner = User::factory()->organizational()->create();
    $member = User::factory()->organizational()->create();
    $event = acc_event($owner);
    acc_join($event, $member, EventRole::Member);
    $line = acc_line($event, $owner, ['name' => 'ردیف محرمانه', 'planned_amount' => 7_000_000]);

    $component = Livewire::actingAs($member)
        ->test(EventBudget::class, ['event' => $event])
        ->assertSee(__('budget.summary_only'))
        ->assertSee('۷٬۰۰۰٬۰۰۰')
        ->assertDontSee('ردیف محرمانه');

    $component->call('setSection', 'lines')->assertSet('section', 'overview');

    $component->call('openLineForm')->assertForbidden();

    Livewire::actingAs($member)
        ->test(EventBudget::class, ['event' => $event])
        ->call('deleteLine', $line->id)
        ->assertForbidden();

    Livewire::actingAs($member)
        ->test(EventBudget::class, ['event' => $event])
        ->call('approve')
        ->assertForbidden();

    expect(BudgetLine::query()->count())->toBe(1);
});

// ------------------------------------------------------------ Livewire flows

it('lets the owner add a revenue line in toman with persian digits', function () {
    $owner = User::factory()->organizational()->create();
    $event = acc_event($owner);

    Livewire::actingAs($owner)
        ->test(EventBudget::class, ['event' => $event])
        ->call('setUnit', 'toman')
        ->call('openLineForm')
        ->set('lineType', 'revenue')
        ->assertSet('lineCategory', BudgetCategory::Tickets->value)
        ->set('lineName', 'ثبت‌نام')
        ->set('lineAmount', '۱٬۲۰۰')
        ->call('saveLine')
        ->assertHasNoErrors()
        ->assertSet('showLineForm', false)
        ->assertSet('error', null);

    $line = BudgetLine::query()->firstOrFail();

    expect($line->planned_amount)->toBe(12_000)
        ->and($line->type->value)->toBe('revenue')
        ->and($line->event_id)->toBe($event->id);
});

it('shows a field error for non-integer amounts', function () {
    $owner = User::factory()->organizational()->create();
    $event = acc_event($owner);

    Livewire::actingAs($owner)
        ->test(EventBudget::class, ['event' => $event])
        ->call('openLineForm')
        ->set('lineName', 'سالن')
        ->set('lineAmount', '12.5')
        ->call('saveLine')
        ->assertHasErrors('lineAmount');

    expect(BudgetLine::query()->count())->toBe(0);
});

it('records a transaction with a jalali date from the UI', function () {
    $owner = User::factory()->organizational()->create();
    $event = acc_event($owner);
    $line = acc_line($event, $owner);

    Livewire::actingAs($owner)
        ->test(EventBudget::class, ['event' => $event])
        ->call('openTxForm', $line->id)
        ->assertSet('txLineId', (string) $line->id)
        ->set('txAmount', '250000')
        ->set('txDate', '۱۴۰۵/۰۹/۰۱')
        ->set('txDescription', 'پیش‌پرداخت')
        ->call('saveTx')
        ->assertHasNoErrors()
        ->assertSet('showTxForm', false);

    $tx = Transaction::query()->firstOrFail();

    expect($tx->amount)->toBe(250_000)
        ->and($tx->date->toDateString())->toBe(Jalali::parse('1405/09/01')->toDateString())
        ->and($tx->created_by)->toBe($owner->id);
});

it('rejects an invalid jalali date in the transaction form', function () {
    $owner = User::factory()->organizational()->create();
    $event = acc_event($owner);
    $line = acc_line($event, $owner);

    Livewire::actingAs($owner)
        ->test(EventBudget::class, ['event' => $event])
        ->call('openTxForm', $line->id)
        ->set('txAmount', '1000')
        ->set('txDate', 'سلام')
        ->call('saveTx')
        ->assertHasErrors('txDate');

    expect(Transaction::query()->count())->toBe(0);
});

it('shows the lock message once the budget is approved', function () {
    $owner = User::factory()->organizational()->create();
    $event = acc_event($owner);
    acc_line($event, $owner);
    app(BudgetService::class)->approve($event, $owner);

    Livewire::actingAs($owner)
        ->test(EventBudget::class, ['event' => $event])
        ->call('setSection', 'lines')
        ->assertSee(trim(explode(':number', __('budget.locked_notice'))[0]))
        ->call('openLineForm')
        ->set('lineName', 'تازه')
        ->set('lineAmount', '100')
        ->call('saveLine')
        ->assertSet('error', __('budget.locked'));

    expect(BudgetLine::query()->count())->toBe(1);
});

it('approves and starts a revision from the UI', function () {
    $owner = User::factory()->organizational()->create();
    $event = acc_event($owner);
    acc_line($event, $owner);

    Livewire::actingAs($owner)
        ->test(EventBudget::class, ['event' => $event])
        ->call('setSection', 'versions')
        ->set('approveNote', 'تأیید اولیه')
        ->call('approve')
        ->assertSet('error', null)
        ->call('startRevision')
        ->assertSet('error', null);

    expect(app(BudgetService::class)->isLocked($event))->toBeFalse()
        ->and(app(BudgetService::class)->versions($event))->toHaveCount(2);
});

it('saves the overrun threshold for organizational accounts only', function () {
    $owner = User::factory()->organizational()->create();
    $event = acc_event($owner);

    Livewire::actingAs($owner)
        ->test(EventBudget::class, ['event' => $event])
        ->set('thresholdInput', '25')
        ->call('saveThreshold')
        ->assertHasNoErrors()
        ->assertSet('error', null);

    expect(app(BudgetService::class)->overrunThreshold($event->fresh()))->toBe(25);

    Livewire::actingAs($owner)
        ->test(EventBudget::class, ['event' => $event])
        ->set('thresholdInput', '5000')
        ->call('saveThreshold')
        ->assertHasErrors('thresholdInput');
});

it('hides full-plan sections from individual accounts', function () {
    $user = User::factory()->individual()->create();
    $event = Event::query()->create([
        'created_by' => $user->id,
        'title' => 'رویداد شخصی',
        'event_type' => 'local',
        'scale' => 'small',
        'status' => 'draft',
    ]);

    Livewire::actingAs($user)
        ->test(EventBudget::class, ['event' => $event])
        ->assertSee(__('budget.basic_notice'))
        ->assertDontSee(__('budget.section_versions'))
        ->call('setSection', 'versions')
        ->assertSet('section', 'overview')
        ->call('approve')
        ->assertSet('error', __('budget.full_only'));
});

// ------------------------------------------------------- tenant isolation

it('cannot touch lines or transactions of another event through the component', function () {
    $owner = User::factory()->organizational()->create();
    $eventA = acc_event($owner);
    $eventB = acc_event($owner, ['title' => 'رویداد دیگر']);
    $foreignLine = acc_line($eventB, $owner, ['name' => 'خط رویداد B']);
    $foreignTx = app(BudgetService::class)->recordTransaction($foreignLine, $owner, ['amount' => 55, 'date' => '2026-11-20']);

    $component = Livewire::actingAs($owner)->test(EventBudget::class, ['event' => $eventA]);

    acc_blocked(fn () => $component->call('editLine', $foreignLine->id));
    acc_blocked(fn () => $component->call('deleteLine', $foreignLine->id));
    acc_blocked(fn () => $component->call('editTx', $foreignTx->id));
    acc_blocked(fn () => $component->call('deleteTx', $foreignTx->id));
    acc_blocked(fn () => $component->call('openTxForm', $foreignLine->id));
    acc_blocked(fn () => $component->call('downloadReceipt', $foreignTx->id));

    // public properties can be tampered with by the client
    acc_blocked(fn () => Livewire::actingAs($owner)
        ->test(EventBudget::class, ['event' => $eventA])
        ->set('editingLineId', $foreignLine->id)
        ->set('lineName', 'هک شده')
        ->set('lineAmount', '1')
        ->call('saveLine'));

    acc_blocked(fn () => Livewire::actingAs($owner)
        ->test(EventBudget::class, ['event' => $eventA])
        ->set('txLineId', (string) $foreignLine->id)
        ->set('txAmount', '999')
        ->set('txDate', '1405/09/01')
        ->call('saveTx'));

    expect($foreignLine->fresh()->name)->toBe('خط رویداد B')
        ->and(BudgetLine::query()->count())->toBe(1)
        ->and(Transaction::query()->count())->toBe(1)
        ->and(Transaction::query()->whereKey($foreignTx->id)->exists())->toBeTrue();
});

it('does not render another event\'s lines', function () {
    $owner = User::factory()->organizational()->create();
    $eventA = acc_event($owner);
    $eventB = acc_event($owner, ['title' => 'رویداد دیگر']);
    acc_line($eventB, $owner, ['name' => 'خط فقط برای B']);

    Livewire::actingAs($owner)
        ->test(EventBudget::class, ['event' => $eventA])
        ->call('setSection', 'lines')
        ->assertDontSee('خط فقط برای B');
});

// ---------------------------------------------------------------- receipts

it('lets managers download receipts and blocks everyone else', function () {
    Storage::fake('local');

    $owner = User::factory()->organizational()->create();
    $member = User::factory()->organizational()->create();
    $event = acc_event($owner);
    acc_join($event, $member, EventRole::Member);
    $line = acc_line($event, $owner);

    $tx = app(BudgetService::class)->recordTransaction(
        $line,
        $owner,
        ['amount' => 500, 'date' => '2026-11-20'],
        UploadedFile::fake()->create('r.pdf', 20, 'application/pdf'),
    );

    Livewire::actingAs($owner)
        ->test(EventBudget::class, ['event' => $event])
        ->call('downloadReceipt', $tx->id)
        ->assertFileDownloaded('receipt-'.$tx->uuid.'.pdf');

    Livewire::actingAs($member)
        ->test(EventBudget::class, ['event' => $event])
        ->call('downloadReceipt', $tx->id)
        ->assertForbidden();
});
