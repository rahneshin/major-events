<?php

use App\Enums\EventRole;
use App\Enums\IncidentSeverity;
use App\Enums\RiskStatus;
use App\Enums\TaskModule;
use App\Models\Event;
use App\Models\Incident;
use App\Models\Risk;
use App\Models\Task;
use App\Models\User;
use App\Services\RiskService;
use Illuminate\Support\Facades\DB;

function rk_service(): RiskService
{
    return app(RiskService::class);
}

/**
 * @param  array<string, mixed>  $data
 */
function rk_risk(Event $event, User $actor, array $data = []): Risk
{
    return rk_service()->create($event, $actor, array_merge([
        'title' => 'ریسک '.fake()->unique()->numerify('####'),
        'category' => 'safety',
        'likelihood' => 3,
        'impact' => 3,
    ], $data));
}

// ------------------------------------------------------------------- score

it('computes score = likelihood × impact and keeps it in sync on update', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);

    $risk = rk_risk($event, $owner, ['likelihood' => 4, 'impact' => 5]);
    expect($risk->score)->toBe(20)
        ->and($risk->level()->value)->toBe('critical');

    $risk = rk_service()->update($risk, $owner, ['likelihood' => 2]);
    expect($risk->score)->toBe(10)
        ->and($risk->level()->value)->toBe('high');

    // even a direct save cannot store an inconsistent score
    $risk->score = 99;
    $risk->save();
    expect($risk->refresh()->score)->toBe(10);
});

it('rejects invalid ratings, empty titles and bad enums', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);

    foreach ([0, 6, -1, 'abc', 2.5, null] as $bad) {
        expect(fn () => rk_risk($event, $owner, ['likelihood' => $bad]))->toThrow(InvalidArgumentException::class);
    }

    expect(fn () => rk_risk($event, $owner, ['title' => '   ']))->toThrow(InvalidArgumentException::class)
        ->and(fn () => rk_risk($event, $owner, ['category' => 'nope']))->toThrow(InvalidArgumentException::class)
        ->and(fn () => rk_risk($event, $owner, ['response' => 'nope']))->toThrow(InvalidArgumentException::class)
        ->and(fn () => rk_risk($event, $owner, ['title' => str_repeat('ا', 161)]))->toThrow(InvalidArgumentException::class);

    expect(Risk::query()->count())->toBe(0);
});

it('defaults response to reduce and status to open', function () {
    $owner = User::factory()->organizational()->create();
    $risk = rk_risk(tk_event($owner), $owner);

    expect($risk->response->value)->toBe('reduce')
        ->and($risk->status)->toBe(RiskStatus::Open);
});

// ------------------------------------------------------------------- owner

it('accepts only non-viewer members of the same event as owner', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $member = User::factory()->create();
    $viewer = User::factory()->create();
    $stranger = User::factory()->create();
    tk_join($event, $member, EventRole::Member);
    tk_join($event, $viewer, EventRole::Viewer);

    expect(rk_risk($event, $owner, ['owner_id' => $member->id])->owner_id)->toBe($member->id);

    expect(fn () => rk_risk($event, $owner, ['owner_id' => $viewer->id]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => rk_risk($event, $owner, ['owner_id' => $stranger->id]))->toThrow(InvalidArgumentException::class);

    // owner can be cleared
    $risk = rk_risk($event, $owner, ['owner_id' => $member->id]);
    expect(rk_service()->update($risk, $owner, ['owner_id' => null])->owner_id)->toBeNull();
});

// -------------------------------------------------------------- plan limits

it('gives individual accounts the simple register only', function () {
    $user = User::factory()->individual()->create();
    $event = tk_event($user);

    // basic: register + status + control task link are available
    $risk = rk_risk($event, $user);
    expect($risk->exists)->toBeTrue();
    rk_service()->setStatus($risk, $user, RiskStatus::Mitigating);

    // full-only features are refused
    expect(fn () => rk_service()->applyTemplate($event, $user, 'medical'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => rk_service()->recordIncident($event, $user, ['title' => 'حادثه']))->toThrow(InvalidArgumentException::class);

    expect(rk_service()->hasBasic($user))->toBeTrue()
        ->and(rk_service()->hasFull($user))->toBeFalse();
});

it('blocks writes on archived or cancelled events', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $risk = rk_risk($event, $owner);

    DB::table('events')->where('id', $event->id)->update(['status' => 'archived']);
    $event->refresh();
    $risk->refresh();

    expect(fn () => rk_risk($event, $owner))->toThrow(InvalidArgumentException::class)
        ->and(fn () => rk_service()->update($risk, $owner, ['title' => 'x']))->toThrow(InvalidArgumentException::class)
        ->and(fn () => rk_service()->recordIncident($event, $owner, ['title' => 'x']))->toThrow(InvalidArgumentException::class);
});

// ----------------------------------------------------- register / matrix / summary

it('lists risks by score and filters by status, category and matrix cell', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);

    $low = rk_risk($event, $owner, ['likelihood' => 1, 'impact' => 2, 'category' => 'legal']);
    $high = rk_risk($event, $owner, ['likelihood' => 4, 'impact' => 4, 'category' => 'medical']);
    $closed = rk_risk($event, $owner, ['likelihood' => 5, 'impact' => 5, 'status' => 'closed']);

    $active = rk_service()->register($event);
    expect($active->pluck('id')->all())->toBe([$high->id, $low->id]);

    expect(rk_service()->register($event, ['status' => 'all'])->first()->id)->toBe($closed->id)
        ->and(rk_service()->register($event, ['status' => 'closed'])->pluck('id')->all())->toBe([$closed->id])
        ->and(rk_service()->register($event, ['category' => 'legal'])->pluck('id')->all())->toBe([$low->id])
        ->and(rk_service()->register($event, ['cell' => '4-4'])->pluck('id')->all())->toBe([$high->id])
        ->and(rk_service()->register($event, ['cell' => '9-9'])->count())->toBe(2); // malformed cell is ignored
});

it('groups active risks into matrix cells', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);

    $a = rk_risk($event, $owner, ['likelihood' => 2, 'impact' => 3]);
    $b = rk_risk($event, $owner, ['likelihood' => 2, 'impact' => 3]);
    rk_risk($event, $owner, ['likelihood' => 5, 'impact' => 5, 'status' => 'closed']);

    $matrix = rk_service()->matrix($event);

    expect($matrix)->toHaveKey('2-3')
        ->and(collect($matrix['2-3'])->pluck('id')->sort()->values()->all())->toBe([$a->id, $b->id])
        ->and($matrix)->not->toHaveKey('5-5');
});

it('summarizes levels, overdue reviews and occurred risks', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);

    rk_risk($event, $owner, ['likelihood' => 5, 'impact' => 5, 'review_date' => now()->subDays(3)]);   // critical, overdue
    rk_risk($event, $owner, ['likelihood' => 2, 'impact' => 2, 'review_date' => now()->addDays(2)]);   // low, soon
    rk_risk($event, $owner, ['likelihood' => 3, 'impact' => 3, 'status' => 'occurred']);               // not active

    $summary = rk_service()->summary($event);

    expect($summary['total'])->toBe(2)
        ->and($summary['by_level']['critical'])->toBe(1)
        ->and($summary['by_level']['low'])->toBe(1)
        ->and($summary['review_overdue'])->toBe(1)
        ->and($summary['review_soon'])->toBe(1)
        ->and($summary['occurred'])->toBe(1)
        ->and($summary['top']->first()->score)->toBe(25);
});

it('does not leak risks across events', function () {
    $owner = User::factory()->organizational()->create();
    $eventA = tk_event($owner);
    $eventB = tk_event($owner);
    rk_risk($eventA, $owner);

    expect(rk_service()->register($eventB))->toBeEmpty()
        ->and(rk_service()->matrix($eventB))->toBe([])
        ->and(rk_service()->summary($eventB)['total'])->toBe(0);
});

// ---------------------------------------------------------------- templates

it('adds template risks once and tags them with the template key', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);

    $first = rk_service()->applyTemplate($event, $owner, 'medical');
    expect($first)->toHaveCount(3)
        ->and($first->every(fn (Risk $r) => $r->template_key !== null && $r->status === RiskStatus::Open))->toBeTrue();

    expect(rk_service()->applyTemplate($event, $owner, 'medical'))->toBeEmpty();
    expect(Risk::query()->count())->toBe(3);

    $single = rk_service()->applyTemplate($event, $owner, 'weather', ['wx_rain']);
    expect($single)->toHaveCount(1)
        ->and($single->first()->template_key)->toBe('wx_rain');

    expect(fn () => rk_service()->applyTemplate($event, $owner, 'unknown'))->toThrow(InvalidArgumentException::class);
});

it('keeps template content consistent', function () {
    $keys = [];

    foreach (config('risk.templates') as $template) {
        foreach ($template['items'] as $item) {
            $keys[] = $item['key'];
            expect($item['likelihood'])->toBeBetween(1, 5)
                ->and($item['impact'])->toBeBetween(1, 5)
                ->and(App\Enums\RiskCategory::tryFrom($item['category']))->not->toBeNull()
                ->and(App\Enums\RiskResponse::tryFrom($item['response']))->not->toBeNull();
        }
    }

    expect($keys)->toBe(array_values(array_unique($keys)));
});

// ------------------------------------------------------------ control tasks

it('links tasks of the same event and rejects foreign ones', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $other = tk_event($owner);
    $risk = rk_risk($event, $owner);

    $task = tk_task($event, $owner);
    $foreign = tk_task($other, $owner);

    rk_service()->linkTask($risk, $owner, $task);
    expect($risk->tasks()->pluck('tasks.id')->all())->toBe([$task->id]);

    expect(fn () => rk_service()->linkTask($risk, $owner, $task))->toThrow(InvalidArgumentException::class)
        ->and(fn () => rk_service()->linkTask($risk, $owner, $foreign))->toThrow(InvalidArgumentException::class);

    rk_service()->unlinkTask($risk, $owner, $task);
    expect($risk->tasks()->count())->toBe(0);
});

it('creates a control task from a risk', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $risk = rk_risk($event, $owner, ['title' => 'ازدحام ورودی', 'likelihood' => 5, 'impact' => 5, 'control_actions' => 'مسیر جدا']);

    $task = rk_service()->createControlTask($risk, $owner);

    expect($task->module)->toBe(TaskModule::Risk)
        ->and($task->event_id)->toBe($event->id)
        ->and($task->priority->value)->toBe('urgent')
        ->and($task->title)->toContain('ازدحام ورودی')
        ->and($risk->tasks()->pluck('tasks.id')->all())->toBe([$task->id]);

    $custom = rk_service()->createControlTask($risk, $owner, ['title' => 'نصب موانع']);
    expect($custom->title)->toBe('نصب موانع');
});

it('detaches but keeps control tasks when a risk is deleted', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $risk = rk_risk($event, $owner);
    $task = rk_service()->createControlTask($risk, $owner);

    rk_service()->delete($risk, $owner);

    expect(Risk::query()->count())->toBe(0)
        ->and(Task::query()->whereKey($task->id)->exists())->toBeTrue()
        ->and(DB::table('risk_task')->count())->toBe(0);
});

// ----------------------------------------------------------------- incidents

it('records, edits, resolves and deletes incidents', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $risk = rk_risk($event, $owner);

    $incident = rk_service()->recordIncident($event, $owner, [
        'title' => 'مصدومیت یک شرکت‌کننده',
        'severity' => 'high',
        'occurred_at' => '2026-11-20 10:30:00',
        'risk_id' => $risk->id,
    ]);

    expect($incident->severity)->toBe(IncidentSeverity::High)
        ->and($incident->reported_by)->toBe($owner->id)
        ->and($incident->risk_id)->toBe($risk->id)
        ->and($incident->isResolved())->toBeFalse();

    $incident = rk_service()->updateIncident($incident, $owner, ['severity' => 'critical']);
    expect($incident->severity)->toBe(IncidentSeverity::Critical);

    expect(fn () => rk_service()->resolveIncident($incident, $owner, '   '))->toThrow(InvalidArgumentException::class);

    $incident = rk_service()->resolveIncident($incident, $owner, 'انتقال به مرکز درمانی');
    expect($incident->isResolved())->toBeTrue()
        ->and($incident->resolution)->toBe('انتقال به مرکز درمانی');

    rk_service()->deleteIncident($incident, $owner);
    expect(Incident::query()->count())->toBe(0);
});

it('rejects an incident linked to a risk of another event', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $other = tk_event($owner);
    $foreignRisk = rk_risk($other, $owner);

    expect(fn () => rk_service()->recordIncident($event, $owner, ['title' => 'حادثه', 'risk_id' => $foreignRisk->id]))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => rk_service()->recordIncident($event, $owner, ['title' => '']))
        ->toThrow(InvalidArgumentException::class);

    expect(Incident::query()->count())->toBe(0);
});

it('keeps the incident when its risk is deleted', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $risk = rk_risk($event, $owner);
    $incident = rk_service()->recordIncident($event, $owner, ['title' => 'حادثه', 'risk_id' => $risk->id]);

    rk_service()->delete($risk, $owner);

    // soft delete keeps the FK row, so the incident stays and still points at it
    expect(Incident::query()->whereKey($incident->id)->exists())->toBeTrue();
});
