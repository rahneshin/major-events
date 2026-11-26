<?php

require_once __DIR__.'/../../Support/RankedTestHelpers.php';

use App\Enums\CompetitionStatus;
use App\Enums\ResultStatus;
use App\Enums\StageStatus;
use App\Models\Competitor;
use App\Models\CompetitionTemplate;
use App\Models\Heat;
use App\Models\HeatResult;
use App\Services\HeatService;

it('seeds the three ranked templates', function () {
    expect(CompetitionTemplate::query()->whereIn('code', ['ranked_heats', 'ranked_final', 'ranked_field'])->count())->toBe(3);

    $template = CompetitionTemplate::query()->where('code', 'ranked_heats')->firstOrFail();

    expect($template->result_type)->toBe('ranked')
        ->and(count($template->stages))->toBe(2)
        ->and($template->stages[1]['is_final'])->toBeTrue()
        ->and($template->scoring_config['better'])->toBe('lower');
});

it('creates a ranked competition with a preliminary and a final stage', function () {
    [, , $competition] = rk_setup('ranked_heats', 0);

    expect($competition->result_type)->toBe('ranked')
        ->and($competition->stages()->count())->toBe(2)
        ->and($competition->stages()->orderBy('position')->first()->is_final)->toBeFalse();
});

it('previews a draw without saving anything', function () {
    [$owner, , $competition] = rk_setup();

    $plan = app(HeatService::class)->preview($competition, 'random', 'seed-1');

    expect($plan['heats'])->toHaveCount(2)
        ->and(collect($plan['heats'])->flatten()->unique()->count())->toBe(10)
        ->and(Heat::query()->count())->toBe(0)
        ->and($competition->refresh()->status)->toBe(CompetitionStatus::Registration);
});

it('is reproducible from the seed', function () {
    [, , $competition] = rk_setup();
    $service = app(HeatService::class);

    expect($service->preview($competition, 'random', 'same')['heats'])
        ->toBe($service->preview($competition, 'random', 'same')['heats']);
});

it('draws balanced heats and creates entries', function () {
    [$owner, , $competition] = rk_setup();

    app(HeatService::class)->draw($competition, $owner, 'random', 'seed-1');

    $heats = rk_heats($competition);
    $first = $competition->stages()->orderBy('position')->first();

    expect($heats)->toHaveCount(2)
        ->and($heats->map(fn ($h) => $h->results->count())->all())->toBe([5, 5])
        ->and($heats->first()->results->pluck('lane')->all())->toBe([1, 2, 3, 4, 5])
        ->and($first->entries()->count())->toBe(10)
        ->and($first->status)->toBe(StageStatus::Drawn)
        ->and($competition->refresh()->status)->toBe(CompetitionStatus::Drawn);
});

it('refuses to draw twice or with too few competitors', function () {
    [$owner, , $competition] = rk_setup();
    $service = app(HeatService::class);
    $service->draw($competition, $owner, 'random', 's');

    expect(fn () => $service->draw($competition->refresh(), $owner, 'random', 's'))
        ->toThrow(InvalidArgumentException::class);

    [$owner2, , $small] = rk_setup('ranked_final', 1);
    expect(fn () => $service->draw($small, $owner2, 'random', 's'))->toThrow(InvalidArgumentException::class);
});

it('uses a single heat for a final-only template', function () {
    [$owner, , $competition] = rk_setup('ranked_final', 12);

    app(HeatService::class)->draw($competition, $owner, 'random', 's');

    expect(rk_heats($competition))->toHaveCount(1)
        ->and(rk_heats($competition)->first()->results)->toHaveCount(12);
});

it('only accepts results after the competition is ready', function () {
    [$owner, , $competition] = rk_setup();
    app(HeatService::class)->draw($competition, $owner, 'random', 's');

    expect(fn () => rk_enter(rk_heats($competition)->first(), $owner, [10, 11, 12, 13, 14]))
        ->toThrow(InvalidArgumentException::class);

    app(HeatService::class)->markReady($competition->refresh(), $owner);

    expect($competition->refresh()->status)->toBe(CompetitionStatus::Ready);
});

it('ranks a heat, confirms it and starts the competition', function () {
    [$owner, , $competition] = rk_setup();
    rk_draw($competition, $owner);

    $heat = rk_enter(rk_heats($competition)->first(), $owner, [10.5, 9.9, 11.0, 10.2, 10.8]);

    $ranks = $heat->results()->pluck('rank', 'lane')->all();

    expect($ranks)->toBe([1 => 3, 2 => 1, 3 => 5, 4 => 2, 5 => 4])
        ->and($heat->result_status)->toBe(ResultStatus::Confirmed)
        ->and($competition->refresh()->status)->toBe(CompetitionStatus::Running);
});

it('requires a value unless the status is DNS, DNF or DQ', function () {
    [$owner, , $competition] = rk_setup();
    rk_draw($competition, $owner);
    $heat = rk_heats($competition)->first();

    expect(fn () => rk_enter($heat, $owner, [10, 11, '', 13, 14]))->toThrow(InvalidArgumentException::class);

    $saved = rk_enter($heat, $owner, [10, 11, 'DNS', 'DQ', 14]);
    $rows = $saved->results()->get()->keyBy('lane');

    expect($rows[3]->rank)->toBeNull()->and($rows[4]->rank)->toBeNull()
        ->and($rows[1]->rank)->toBe(1)->and($rows[5]->rank)->toBe(3);
});

it('rejects malformed values and competitors from another heat', function () {
    [$owner, , $competition] = rk_setup();
    rk_draw($competition, $owner);
    [$a, $b] = rk_heats($competition)->all();

    expect(fn () => rk_enter($a, $owner, ['abc', 1, 2, 3, 4]))->toThrow(InvalidArgumentException::class);

    $foreign = $b->results->first()->competitor_id;
    expect(fn () => app(HeatService::class)->enterResults($a->refresh(), $owner, [$foreign => ['value' => '10', 'status' => 'ok']], true))
        ->toThrow(InvalidArgumentException::class);
});

it('keeps scorer entries unconfirmed until a manager confirms', function () {
    [$owner, , $competition] = rk_setup();
    rk_draw($competition, $owner);

    $heat = rk_enter(rk_heats($competition)->first(), $owner, [10, 11, 12, 13, 14], canConfirm: false);

    expect($heat->result_status)->toBe(ResultStatus::Entered)
        ->and(fn () => rk_enter($heat, $owner, [10, 11, 12, 13, 15], false))->not->toThrow(InvalidArgumentException::class);

    $confirmed = app(HeatService::class)->confirmHeat($heat->refresh(), $owner);

    expect($confirmed->result_status)->toBe(ResultStatus::Confirmed);

    // سطح ورودی نمی‌تواند نتیجه‌ی تأییدشده را ویرایش کند
    expect(fn () => rk_enter($confirmed, $owner, [9, 11, 12, 13, 15], false))->toThrow(InvalidArgumentException::class);
});

it('treats a withdrawn competitor as DNS', function () {
    [$owner, , $competition] = rk_setup();
    rk_draw($competition, $owner);
    $heat = rk_heats($competition)->first();
    $withdrawn = $heat->results->first()->competitor;
    $withdrawn->update(['status' => 'withdrawn']);

    $saved = rk_enter($heat, $owner, [10, 11, 12, 13, 14]);
    $row = $saved->results()->where('competitor_id', $withdrawn->id)->first();

    expect($row->status->value)->toBe('DNS')->and($row->value)->toBeNull()->and($row->rank)->toBeNull();
});

it('refuses to advance until every heat is confirmed', function () {
    [$owner, , $competition] = rk_setup();
    rk_draw($competition, $owner);
    rk_enter(rk_heats($competition)->first(), $owner, [10, 11, 12, 13, 14]);

    expect(fn () => app(HeatService::class)->advance($competition->refresh(), $owner))
        ->toThrow(InvalidArgumentException::class);
});

it('advances the first two of each heat plus the two best remaining into the final', function () {
    [$owner, , $competition] = rk_setup();
    rk_draw($competition, $owner);
    $times = rk_enter_stage($competition, $owner);

    $result = app(HeatService::class)->advance($competition->refresh(), $owner);

    $final = rk_heats($competition, 2);
    $finalIds = $final->first()->results->pluck('competitor_id')->all();
    $advancedIds = HeatResult::query()->where('advanced', true)->pluck('competitor_id')->all();

    expect($result['advancers'])->toBe(6)
        ->and($result['tied_at_cutoff'])->toBeFalse()
        ->and($final)->toHaveCount(1)
        ->and($final->first()->name)->toBe('فینال')
        ->and($finalIds)->toEqualCanonicalizing($advancedIds)
        ->and($competition->stages()->orderBy('position')->first()->status)->toBe(StageStatus::Finished);

    // لاین ۱ فینال سریع‌ترین زمان مرحله‌ی مقدماتی است
    $fastest = collect($times)->sort()->keys()->first();
    expect($final->first()->results->firstWhere('lane', 1)->competitor_id)->toBe($fastest);
});

it('cannot advance twice', function () {
    [$owner, , $competition] = rk_setup();
    rk_draw($competition, $owner);
    rk_enter_stage($competition, $owner);
    $service = app(HeatService::class);
    $service->advance($competition->refresh(), $owner);

    expect(fn () => $service->advance($competition->refresh(), $owner))->toThrow(InvalidArgumentException::class);
});

it('finishes the competition and fills final ranks when the final is confirmed', function () {
    [$owner, , $competition] = rk_setup();
    rk_draw($competition, $owner);
    rk_enter_stage($competition, $owner);
    app(HeatService::class)->advance($competition->refresh(), $owner);

    // در فینال هر که مسیر بالاتری دارد سریع‌تر است
    $finalHeat = rk_heats($competition, 2)->first();
    rk_enter($finalHeat, $owner, [20, 19, 18, 17, 16, 15]);

    $competition->refresh();
    $byLane = $finalHeat->results()->orderBy('lane')->pluck('competitor_id', 'lane')->all();
    $rank = fn (int $lane) => Competitor::query()->find($byLane[$lane])->final_rank;

    expect($competition->status)->toBe(CompetitionStatus::Finished)
        ->and($rank(6))->toBe(1)->and($rank(5))->toBe(2)->and($rank(1))->toBe(6);

    // حذف‌شدگان بعد از فینالیست‌ها رتبه می‌گیرند و رتبه‌ها یکتا و کامل‌اند
    $ranks = $competition->competitors()->pluck('final_rank')->all();
    expect($ranks)->toHaveCount(10)->and(collect($ranks)->sort()->values()->all())->toBe(range(1, 10));
});

it('ranks only valid finishers when someone is disqualified in a direct final', function () {
    [$owner, , $competition] = rk_setup('ranked_final', 4);
    rk_draw($competition, $owner);

    rk_enter(rk_heats($competition)->first(), $owner, [9.5, 'DQ', 10.5, 10.0]);

    $byLane = rk_heats($competition)->first()->results->keyBy('lane');
    $finalRank = fn (int $lane) => $byLane[$lane]->competitor->fresh()->final_rank;

    expect($competition->refresh()->status)->toBe(CompetitionStatus::Finished)
        ->and($finalRank(1))->toBe(1)->and($finalRank(4))->toBe(2)->and($finalRank(3))->toBe(3)
        ->and($finalRank(2))->toBeNull();
});

it('treats larger values as better for field events and shares tied ranks', function () {
    [$owner, , $competition] = rk_setup('ranked_field', 4);
    rk_draw($competition, $owner);

    rk_enter(rk_heats($competition)->first(), $owner, [5.0, 7.2, 6.1, 6.1]);

    $byLane = rk_heats($competition)->first()->results->keyBy('lane');
    $finalRank = fn (int $lane) => $byLane[$lane]->competitor->fresh()->final_rank;

    expect($finalRank(2))->toBe(1)->and($finalRank(3))->toBe(2)->and($finalRank(4))->toBe(2)->and($finalRank(1))->toBe(4);
});

it('blocks editing a stage once the next one exists and allows it after rollback', function () {
    [$owner, , $competition] = rk_setup();
    rk_draw($competition, $owner);
    rk_enter_stage($competition, $owner);
    $service = app(HeatService::class);
    $service->advance($competition->refresh(), $owner);
    $prelim = rk_heats($competition)->first();

    expect(fn () => rk_enter($prelim, $owner, [10, 11, 12, 13, 14]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $service->clearHeat($prelim, $owner))->toThrow(InvalidArgumentException::class);

    $service->rollbackAdvance($competition->refresh(), $owner);

    expect(rk_heats($competition, 2))->toHaveCount(0)
        ->and(HeatResult::query()->where('advanced', true)->count())->toBe(0)
        ->and(fn () => rk_enter($prelim, $owner, [10, 11, 12, 13, 14]))->not->toThrow(InvalidArgumentException::class);
});

it('refuses to roll back a stage that already has results', function () {
    [$owner, , $competition] = rk_setup();
    rk_draw($competition, $owner);
    rk_enter_stage($competition, $owner);
    $service = app(HeatService::class);
    $service->advance($competition->refresh(), $owner);
    $final = rk_heats($competition, 2)->first();
    rk_enter($final, $owner, [11, 12, 13, 14, 15, 16], canConfirm: false);

    expect(fn () => $service->rollbackAdvance($competition->refresh(), $owner))->toThrow(InvalidArgumentException::class);
});

it('resets the draw only before any result is recorded', function () {
    [$owner, , $competition] = rk_setup();
    rk_draw($competition, $owner);
    $service = app(HeatService::class);

    $service->reset($competition->refresh(), $owner);

    expect(Heat::query()->count())->toBe(0)
        ->and($competition->refresh()->status)->toBe(CompetitionStatus::Registration)
        ->and($competition->stages()->first()->entries()->count())->toBe(0);

    rk_draw($competition, $owner, 'again');
    rk_enter(rk_heats($competition)->first(), $owner, [10, 11, 12, 13, 14]);

    expect(fn () => $service->reset($competition->refresh(), $owner))->toThrow(InvalidArgumentException::class);
});

it('reopens a finished competition so the final can be corrected', function () {
    [$owner, , $competition] = rk_setup('ranked_final', 3);
    rk_draw($competition, $owner);
    $heat = rk_enter(rk_heats($competition)->first(), $owner, [10, 11, 12]);
    $service = app(HeatService::class);

    expect($competition->refresh()->status)->toBe(CompetitionStatus::Finished)
        ->and(fn () => rk_enter($heat, $owner, [12, 11, 10]))->toThrow(InvalidArgumentException::class);

    $service->reopen($competition, $owner);

    expect($competition->refresh()->status)->toBe(CompetitionStatus::Running)
        ->and($competition->competitors()->whereNotNull('final_rank')->count())->toBe(0);

    rk_enter($heat, $owner, [12, 11, 10]);
    $byLane = rk_heats($competition)->first()->results->keyBy('lane');

    expect($competition->refresh()->status)->toBe(CompetitionStatus::Finished)
        ->and($byLane[3]->competitor->fresh()->final_rank)->toBe(1);
});

it('configures the preliminary stage only before the draw', function () {
    [$owner, , $competition] = rk_setup();
    $service = app(HeatService::class);
    $prelim = $competition->stages()->orderBy('position')->first();
    $final = $competition->stages()->orderBy('position')->get()->last();

    $service->configureStage($prelim, $owner, ['heat_size' => 4, 'advance_per_heat' => 1, 'advance_best_remaining' => 3]);

    expect($prelim->refresh()->config('heat_size'))->toBe(4)
        ->and(fn () => $service->configureStage($prelim, $owner, ['heat_size' => 1]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $service->configureStage($prelim, $owner, ['advance_per_heat' => 0, 'advance_best_remaining' => 0]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $service->configureStage($final, $owner, ['heat_size' => 4]))->toThrow(InvalidArgumentException::class);

    $service->draw($competition, $owner, 'random', 's');

    expect(rk_heats($competition))->toHaveCount(3)
        ->and(fn () => $service->configureStage($prelim->refresh(), $owner, ['heat_size' => 6]))->toThrow(InvalidArgumentException::class);
});

it('rejects ranked operations on a match-based competition', function () {
    $owner = cp_owner();
    $event = tk_event($owner);
    $league = cp_competition($event, $owner);
    cp_fill($league, $owner, 4);

    expect(fn () => app(HeatService::class)->preview($league, 'random', 's'))->toThrow(InvalidArgumentException::class);
});
