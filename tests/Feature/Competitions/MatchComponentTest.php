<?php

require_once __DIR__.'/../../Support/CompetitionTestHelpers.php';

use App\Enums\CompetitionRole;
use App\Enums\ResultStatus;
use App\Livewire\Competitions\CompetitionShow;
use App\Models\Competition;
use App\Models\User;
use App\Services\CompetitionService;
use Livewire\Livewire;

/** @return array{0: User, 1: User, 2: Competition} owner, scorer, competition (ready) */
function mc_setup(): array
{
    $owner = cp_owner();
    $event = tk_event($owner);
    $competition = cp_competition($event, $owner);
    cp_fill($competition, $owner, 4);
    cp_draw($competition, $owner);
    app(CompetitionService::class)->markReady($competition->refresh(), $owner);

    $scorer = User::factory()->create();
    tk_join($event, $scorer);
    app(CompetitionService::class)->assignRole($competition, $owner, $scorer, CompetitionRole::Scorer);

    return [$owner, $scorer, $competition->refresh()];
}

function mc_page(User $user, Competition $competition)
{
    return Livewire::actingAs($user)->test(CompetitionShow::class, ['event' => $competition->event, 'competition' => $competition]);
}

it('lets a scorer enter a result that then waits for the manager', function () {
    [$owner, $scorer, $competition] = mc_setup();
    $match = $competition->firstStage()->matches()->orderBy('id')->first();

    mc_page($scorer, $competition)
        ->call('setSection', 'fixtures')
        ->call('startResult', $match->id)
        ->set('mScoreA', '2')
        ->set('mScoreB', '1')
        ->call('saveResult')
        ->assertSet('error', null)
        ->assertSet('editingMatchId', null);

    expect($match->refresh()->result_status)->toBe(ResultStatus::Entered)
        ->and($match->entered_by)->toBe($scorer->id);

    mc_page($owner, $competition)->call('confirmMatch', $match->id)->assertSet('error', null);

    expect($match->refresh()->result_status)->toBe(ResultStatus::Confirmed);
});

it('forbids a scorer from confirming, clearing, scheduling or adding matches', function () {
    [$owner, $scorer, $competition] = mc_setup();
    $match = $competition->firstStage()->matches()->orderBy('id')->first();

    $page = mc_page($scorer, $competition);

    $page->call('confirmMatch', $match->id)->assertForbidden();
    mc_page($scorer, $competition)->call('clearMatch', $match->id)->assertForbidden();
    mc_page($scorer, $competition)->call('walkoverMatch', $match->id, 'a')->assertForbidden();
    mc_page($scorer, $competition)->call('startSchedule', $match->id)->assertForbidden();
    mc_page($scorer, $competition)->call('addManualMatch')->assertForbidden();
    mc_page($scorer, $competition)->call('reopen')->assertForbidden();

    expect($match->refresh()->result_status)->toBeNull();
});

it('forbids a plain member from entering results', function () {
    [$owner, $scorer, $competition] = mc_setup();
    $member = User::factory()->create();
    tk_join($competition->event, $member);
    $match = $competition->firstStage()->matches()->orderBy('id')->first();

    mc_page($member, $competition)->call('startResult', $match->id)->assertForbidden();
});

it('does not touch matches of another competition through the component', function () {
    [$owner, $scorer, $competition] = mc_setup();

    $other = cp_competition($competition->event, $owner, 'school_league', 'دیگر');
    cp_fill($other, $owner, 3);
    cp_draw($other, $owner);
    app(CompetitionService::class)->markReady($other->refresh(), $owner);
    $foreign = $other->firstStage()->matches()->first();

    tk_blocked(function () use ($owner, $competition, $foreign) {
        mc_page($owner, $competition)->call('confirmMatch', $foreign->id);
    });
    tk_blocked(function () use ($owner, $competition, $foreign) {
        mc_page($owner, $competition)->call('startResult', $foreign->id);
    });

    expect($foreign->refresh()->result_status)->toBeNull();
});

it('hides other competitions matches from the edit panel even with a forged id', function () {
    [$owner, $scorer, $competition] = mc_setup();
    $own = $competition->firstStage()->matches()->orderBy('id')->first();

    $other = cp_competition($competition->event, $owner, 'school_league', 'دیگر');
    cp_fill($other, $owner, 3);
    cp_draw($other, $owner);
    $foreign = $other->firstStage()->matches()->first();

    mc_page($owner, $competition)
        ->call('setSection', 'fixtures')
        ->set('editingMatchId', $foreign->id)
        ->assertDontSee(__('competition.save_result'))
        ->set('editingMatchId', $own->id)
        ->assertSee(__('competition.save_result'));
});

it('renders the round list and the league matrix', function () {
    [$owner, $scorer, $competition] = mc_setup();
    $first = $competition->firstStage()->matches()->with(['a', 'b'])->orderBy('id')->first();

    mc_page($owner, $competition)
        ->call('setSection', 'fixtures')
        ->assertSee($first->a->name)
        ->call('setFixturesView', 'matrix')
        ->assertOk()
        ->assertSee($first->b->name)
        ->call('setRound', '2')
        ->call('setFixturesView', 'rounds')
        ->assertOk();
});

it('saves a schedule from the component and rejects a clash with a message', function () {
    [$owner, $scorer, $competition] = mc_setup();
    $matches = $competition->firstStage()->matches()->orderBy('id')->get();
    $first = $matches[0];
    $ids = [$first->a_id, $first->b_id];
    $sameTeam = $matches->first(fn ($m) => $m->id !== $first->id && (in_array($m->a_id, $ids) || in_array($m->b_id, $ids)));

    mc_page($owner, $competition)
        ->call('setSection', 'fixtures')
        ->call('startSchedule', $first->id)
        ->set('scDate', '2026-12-05')
        ->set('scTime', '10:00')
        ->set('scCourt', 'سالن ۱')
        ->call('saveSchedule')
        ->assertSet('error', null)
        ->call('startSchedule', $sameTeam->id)
        ->set('scDate', '2026-12-05')
        ->set('scTime', '10:30')
        ->call('saveSchedule')
        ->assertSet('error', __('competition.errors.schedule_competitor_clash'));

    expect($first->refresh()->scheduled_at->format('Y-m-d H:i'))->toBe('2026-12-05 10:00')
        ->and($first->court)->toBe('سالن ۱')
        ->and($sameTeam->refresh()->scheduled_at)->toBeNull();
});

it('rejects a malformed schedule date', function () {
    [$owner, $scorer, $competition] = mc_setup();
    $match = $competition->firstStage()->matches()->first();

    mc_page($owner, $competition)
        ->call('startSchedule', $match->id)
        ->set('scDate', 'tomorrow')
        ->call('saveSchedule')
        ->assertSet('error', __('competition.errors.schedule_invalid'));
});

it('imports results through the component', function () {
    [$owner, $scorer, $competition] = mc_setup();
    $match = $competition->firstStage()->matches()->with(['a', 'b'])->orderBy('id')->first();

    mc_page($owner, $competition)
        ->call('setSection', 'fixtures')
        ->set('importText', "{$match->round}, {$match->a->name}, {$match->b->name}, 4, 2")
        ->call('importResults')
        ->assertSet('error', null);

    expect([$match->refresh()->score_a, $match->score_b])->toBe([4, 2]);
});

it('lets a manager withdraw a team after the draw from the component', function () {
    [$owner, $scorer, $competition] = mc_setup();
    $competitor = $competition->competitors()->first();

    mc_page($owner, $competition)->call('withdrawCompetitor', $competitor->id)->assertSet('error', null);

    expect($competitor->refresh()->status->value)->toBe('withdrawn');
});
