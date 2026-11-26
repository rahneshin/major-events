<?php

require_once __DIR__.'/../../Support/RankedTestHelpers.php';

use App\Enums\CompetitionRole;
use App\Enums\CompetitionStatus;
use App\Enums\ResultStatus;
use App\Livewire\Competitions\CompetitionShow;
use App\Livewire\Competitions\PublicLinks;
use App\Livewire\Competitions\RankedShow;
use App\Livewire\Events\EventCompetitions;
use App\Models\Competition;
use App\Models\User;
use App\Services\CompetitionService;
use App\Services\PublicLinkService;
use Livewire\Livewire;

beforeEach(fn () => $this->withoutVite());

function rs_page(User $user, Competition $competition)
{
    return Livewire::actingAs($user)->test(RankedShow::class, ['event' => $competition->event, 'competition' => $competition]);
}

/** @return array{0: User, 1: Competition} scorer، ورزش */
function rs_scorer(Competition $competition, User $owner): array
{
    $scorer = User::factory()->create();
    tk_join($competition->event, $scorer);
    app(CompetitionService::class)->assignRole($competition, $owner, $scorer, CompetitionRole::Scorer);

    return [$scorer, $competition];
}

it('shows the ranked page to members and forbids outsiders', function () {
    [$owner, $event, $competition] = rk_setup('ranked_heats', 0);

    $this->actingAs($owner)
        ->get(route('competitions.ranked', ['event' => $event, 'competition' => $competition]))
        ->assertOk()
        ->assertSee($competition->name);

    $this->actingAs(cp_owner())
        ->get(route('competitions.ranked', ['event' => $event, 'competition' => $competition]))
        ->assertForbidden();
});

it('returns 404 when the ranked competition belongs to another event', function () {
    [$owner, , $competition] = rk_setup('ranked_heats', 0);

    $this->actingAs($owner)
        ->get(route('competitions.ranked', ['event' => tk_event($owner), 'competition' => $competition]))
        ->assertNotFound();
});

it('redirects the generic page of a ranked competition to the ranked page', function () {
    [$owner, $event, $competition] = rk_setup('ranked_heats', 0);

    Livewire::actingAs($owner)
        ->test(CompetitionShow::class, ['event' => $event, 'competition' => $competition])
        ->assertRedirect(route('competitions.ranked', ['event' => $event, 'competition' => $competition]));
});

it('redirects a match-based competition away from the ranked page', function () {
    $owner = cp_owner();
    $event = tk_event($owner);
    $league = cp_competition($event, $owner);

    Livewire::actingAs($owner)
        ->test(RankedShow::class, ['event' => $event, 'competition' => $league])
        ->assertRedirect(route('competitions.show', ['event' => $event, 'competition' => $league]));
});

it('registers, previews, draws and readies a ranked competition through the page', function () {
    [$owner, , $competition] = rk_setup('ranked_heats', 0);

    $page = rs_page($owner, $competition)
        ->set('rName', 'سارا احمدی')
        ->set('rAffiliation', 'مدرسه الف')
        ->call('register')
        ->assertSet('error', null);

    expect($competition->competitors()->count())->toBe(1);

    rk_fill($competition, $owner, 5, 'دونده ');

    $page->call('setSection', 'heats')
        ->set('dSeed', 'ui-seed')
        ->call('previewDraw')
        ->assertSet('error', null)
        ->assertNotSet('preview', null)
        ->call('confirmDraw')
        ->assertSet('error', null)
        ->assertSet('preview', null);

    expect($competition->refresh()->status)->toBe(CompetitionStatus::Drawn);

    $page->call('markReady')->assertSet('error', null);

    expect($competition->refresh()->status)->toBe(CompetitionStatus::Ready);
});

it('lets the manager enter a heat result through the page', function () {
    [$owner, , $competition] = rk_setup();
    rk_draw($competition, $owner);
    $heat = rk_heats($competition)->first();

    $page = rs_page($owner, $competition)->call('setSection', 'heats')->call('openHeat', $heat->id)
        ->assertSet('editingHeatId', $heat->id);

    foreach ($heat->results as $i => $row) {
        $page->set("entry.{$row->competitor_id}.value", (string) (10 + $i));
    }

    $page->call('saveHeat')->assertSet('error', null)->assertSet('editingHeatId', null);

    expect($heat->refresh()->result_status)->toBe(ResultStatus::Confirmed)
        ->and($heat->results()->orderBy('lane')->pluck('rank')->all())->toBe([1, 2, 3, 4, 5]);
});

it('shows a validation message instead of saving an incomplete heat', function () {
    [$owner, , $competition] = rk_setup();
    rk_draw($competition, $owner);
    $heat = rk_heats($competition)->first();

    rs_page($owner, $competition)
        ->call('openHeat', $heat->id)
        ->call('saveHeat')
        ->assertNotSet('error', null);

    expect($heat->refresh()->result_status)->toBeNull();
});

it('lets a scorer enter a result that waits for the manager', function () {
    [$owner, , $competition] = rk_setup();
    rk_draw($competition, $owner);
    [$scorer] = rs_scorer($competition, $owner);
    $heat = rk_heats($competition)->first();

    $page = rs_page($scorer, $competition)->call('openHeat', $heat->id);
    foreach ($heat->results as $i => $row) {
        $page->set("entry.{$row->competitor_id}.value", (string) (10 + $i));
    }
    $page->call('saveHeat')->assertSet('error', null);

    expect($heat->refresh()->result_status)->toBe(ResultStatus::Entered);

    rs_page($scorer, $competition)->call('confirmHeat', $heat->id)->assertForbidden();
    rs_page($scorer, $competition)->call('clearHeat', $heat->id)->assertForbidden();
    rs_page($scorer, $competition)->call('advance')->assertForbidden();
    rs_page($scorer, $competition)->set('rName', 'x')->call('register')->assertForbidden();

    rs_page($owner, $competition)->call('confirmHeat', $heat->id)->assertSet('error', null);

    expect($heat->refresh()->result_status)->toBe(ResultStatus::Confirmed);

    // سطح ورودی نتیجه‌ی تأییدشده را نمی‌تواند باز کند
    rs_page($scorer, $competition)->call('openHeat', $heat->id)->assertSet('editingHeatId', null)->assertNotSet('error', null);
});

it('forbids a plain member from entering results', function () {
    [$owner, $event, $competition] = rk_setup();
    rk_draw($competition, $owner);
    $member = User::factory()->create();
    tk_join($event, $member);

    rs_page($member, $competition)
        ->call('openHeat', rk_heats($competition)->first()->id)
        ->assertForbidden();
});

it('cannot open a heat that belongs to another competition', function () {
    [$owner, $event, $competition] = rk_setup();
    rk_draw($competition, $owner);

    $other = cp_competition($event, $owner, 'ranked_heats', 'پرش');
    rk_fill($other, $owner, 6);
    rk_draw($other, $owner);
    $foreign = rk_heats($other)->first();

    rs_page($owner, $competition)->call('openHeat', $foreign->id)->assertNotFound();
});

it('advances to the final through the page', function () {
    [$owner, , $competition] = rk_setup();
    rk_draw($competition, $owner);
    rk_enter_stage($competition, $owner);

    rs_page($owner, $competition)->call('setSection', 'heats')->call('advance')->assertSet('error', null);

    expect(rk_heats($competition, 2))->toHaveCount(1)->and(rk_heats($competition, 2)->first()->results)->toHaveCount(6);
});

it('configures the preliminary stage through the page', function () {
    [$owner, , $competition] = rk_setup();
    $stage = $competition->stages()->orderBy('position')->first();

    rs_page($owner, $competition)
        ->set('cHeatSize', '4')->set('cPerHeat', '1')->set('cBest', '2')
        ->call('saveStageConfig', $stage->id)
        ->assertSet('error', null);

    expect($stage->refresh()->config('heat_size'))->toBe(4)->and($stage->config('advance_per_heat'))->toBe(1);

    rs_page($owner, $competition)
        ->set('cHeatSize', '1')
        ->call('saveStageConfig', $stage->id)
        ->assertNotSet('error', null);
});

it('blocks deleting a ranked competition that has results', function () {
    [$owner, , $competition] = rk_setup();
    rk_draw($competition, $owner);
    rk_enter(rk_heats($competition)->first(), $owner, [10, 11, 12, 13, 14]);

    rs_page($owner, $competition)->call('deleteCompetition')->assertSet('error', __('ranked.errors.has_results'));

    expect(Competition::query()->whereKey($competition->id)->exists())->toBeTrue();
});

it('deletes a ranked competition without results', function () {
    [$owner, , $competition] = rk_setup('ranked_final', 0);

    rs_page($owner, $competition)->call('deleteCompetition')->assertRedirect();

    expect(Competition::query()->whereKey($competition->id)->exists())->toBeFalse();
});

it('embeds the public link panel on the settings tab for managers only', function () {
    [$owner, , $competition] = rk_setup('ranked_heats', 0);
    [$scorer] = rs_scorer($competition, $owner);

    rs_page($owner, $competition)->call('setSection', 'settings')->assertSee(__('publicpage.links.title'));
    rs_page($scorer, $competition)->call('setSection', 'settings')->assertDontSee(__('publicpage.links.title'));
});

it('shows the medal table and public links under the competitions tab', function () {
    [$owner, $event] = rk_setup('ranked_heats', 0);

    Livewire::actingAs($owner)
        ->test(EventCompetitions::class, ['event' => $event])
        ->assertSee(__('medals.title'))
        ->assertSee(__('publicpage.links.title'));
});

it('manages public links from the event-level panel', function () {
    [$owner, $event, $competition] = rk_setup('ranked_heats', 0);
    $page = Livewire::actingAs($owner)->test(PublicLinks::class, ['event' => $event]);

    $page->call('enable', $competition->id)->assertSet('error', null);
    $token = $competition->refresh()->public_token;
    expect($token)->not->toBeNull();

    $page->call('rotate', $competition->id);
    expect($competition->refresh()->public_token)->not->toBe($token);

    $page->call('toggleAbbreviate', $competition->id);
    expect(app(PublicLinkService::class)->abbreviatesNames($competition->refresh()))->toBeTrue();

    $page->call('disable', $competition->id);
    expect($competition->refresh()->public_token)->toBeNull();
});

it('forbids a scorer from managing public links and hides other events', function () {
    [$owner, $event, $competition] = rk_setup('ranked_heats', 0);
    [$scorer] = rs_scorer($competition, $owner);

    Livewire::actingAs($scorer)
        ->test(PublicLinks::class, ['event' => $event])
        ->assertDontSee($competition->name)
        ->call('enable', $competition->id)
        ->assertForbidden();

    expect($competition->refresh()->public_token)->toBeNull();

    $foreign = cp_competition(tk_event($owner), $owner);

    Livewire::actingAs($owner)
        ->test(PublicLinks::class, ['event' => $event])
        ->call('enable', $foreign->id)
        ->assertNotFound();
});
