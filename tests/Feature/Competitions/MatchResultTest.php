<?php

require_once __DIR__.'/../../Support/CompetitionTestHelpers.php';

use App\Enums\CompetitionStatus;
use App\Enums\ResultStatus;
use App\Enums\StageStatus;
use App\Models\Competition;
use App\Models\Competitor;
use App\Models\SportMatch;
use App\Services\CompetitionService;
use App\Services\MatchService;
use App\Services\RegistrationService;
use App\Services\StandingsService;
use Carbon\Carbon;

/** @return array{0: App\Models\User, 1: Competition} */
function mr_league(int $teams = 4, string $template = 'school_league'): array
{
    $owner = cp_owner();
    $competition = cp_competition(tk_event($owner), $owner, $template);
    cp_fill($competition, $owner, $teams);
    cp_draw($competition, $owner);
    app(CompetitionService::class)->markReady($competition->refresh(), $owner);

    return [$owner, $competition->refresh()];
}

function mr_matches(Competition $competition)
{
    return $competition->firstStage()->matches()->orderBy('id')->get();
}

it('blocks results until the competition is ready', function () {
    $owner = cp_owner();
    $competition = cp_competition(tk_event($owner), $owner);
    cp_fill($competition, $owner, 3);
    cp_draw($competition, $owner);

    $match = mr_matches($competition)->first();

    expect(fn () => app(MatchService::class)->enterResult($match, $owner, ['score_a' => 1, 'score_b' => 0], true))
        ->toThrow(InvalidArgumentException::class);
});

it('confirms a manager result directly and starts the competition', function () {
    [$owner, $competition] = mr_league();
    $match = mr_matches($competition)->first();

    $result = app(MatchService::class)->enterResult($match, $owner, ['score_a' => 2, 'score_b' => 1], true);

    expect($result->result_status)->toBe(ResultStatus::Confirmed)
        ->and($result->status->value)->toBe('finished')
        ->and((int) $result->winner_id)->toBe((int) $match->a_id)
        ->and($result->confirmed_by)->toBe($owner->id)
        ->and($competition->refresh()->status)->toBe(CompetitionStatus::Running)
        ->and($competition->firstStage()->status)->toBe(StageStatus::Running);
});

it('keeps a scorer result pending until a manager confirms it', function () {
    [$owner, $competition] = mr_league();
    $match = mr_matches($competition)->first();
    $service = app(MatchService::class);

    $entered = $service->enterResult($match, $owner, ['score_a' => 3, 'score_b' => 0], false);

    expect($entered->result_status)->toBe(ResultStatus::Entered)
        ->and($entered->confirmed_by)->toBeNull()
        ->and(array_sum(array_column(app(StandingsService::class)->forStage($competition->firstStage()), 'played')))->toBe(0);

    $confirmed = $service->confirmResult($entered, $owner);

    expect($confirmed->result_status)->toBe(ResultStatus::Confirmed)
        ->and(array_sum(array_column(app(StandingsService::class)->forStage($competition->firstStage()), 'played')))->toBe(2);

    expect(fn () => $service->confirmResult($confirmed, $owner))->toThrow(InvalidArgumentException::class);
});

it('auto confirms when the competition does not require confirmation', function () {
    [$owner, $competition] = mr_league();
    app(CompetitionService::class)->update($competition, $owner, ['requires_confirmation' => false]);

    $result = app(MatchService::class)->enterResult(mr_matches($competition)->first(), $owner, ['score_a' => 1, 'score_b' => 1], false);

    expect($result->result_status)->toBe(ResultStatus::Confirmed)
        ->and($result->winner_id)->toBeNull();
});

it('stops a scorer from overwriting a confirmed result', function () {
    [$owner, $competition] = mr_league();
    $service = app(MatchService::class);
    $match = $service->enterResult(mr_matches($competition)->first(), $owner, ['score_a' => 1, 'score_b' => 0], true);

    expect(fn () => $service->enterResult($match, $owner, ['score_a' => 0, 'score_b' => 5], false))->toThrow(InvalidArgumentException::class);

    $changed = $service->enterResult($match->refresh(), $owner, ['score_a' => 0, 'score_b' => 5], true);

    expect($changed->score_b)->toBe(5)
        ->and((int) $changed->winner_id)->toBe((int) $match->b_id);
});

it('rejects invalid scores', function (mixed $a, mixed $b) {
    [$owner, $competition] = mr_league();

    expect(fn () => app(MatchService::class)->enterResult(mr_matches($competition)->first(), $owner, ['score_a' => $a, 'score_b' => $b], true))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'negative' => [-1, 0],
    'text' => ['abc', 1],
    'empty' => ['', ''],
    'too big' => [1000, 0],
    'decimal' => ['1.5', 0],
]);

it('clears a result and returns the competition to ready when nothing is left', function () {
    [$owner, $competition] = mr_league();
    $service = app(MatchService::class);
    $match = $service->enterResult(mr_matches($competition)->first(), $owner, ['score_a' => 1, 'score_b' => 0], true);

    $cleared = $service->clearResult($match, $owner);

    expect($cleared->result_status)->toBeNull()
        ->and($cleared->score_a)->toBeNull()
        ->and($cleared->status->value)->toBe('scheduled')
        ->and($competition->refresh()->status)->toBe(CompetitionStatus::Ready)
        ->and($competition->hasConfirmedResults())->toBeFalse();
});

it('records a walkover as a confirmed win without scores', function () {
    [$owner, $competition] = mr_league();
    $match = mr_matches($competition)->first();

    $result = app(MatchService::class)->walkover($match, $owner, 'b');

    expect($result->status->value)->toBe('walkover')
        ->and((int) $result->winner_id)->toBe((int) $match->b_id)
        ->and($result->score_a)->toBeNull()
        ->and($result->result_status)->toBe(ResultStatus::Confirmed);

    $rows = collect(app(StandingsService::class)->forStage($competition->firstStage()));
    expect($rows->firstWhere('competitor_id', (int) $match->b_id)['points'])->toBe(3);
});

it('finishes the competition with final ranks and locks it until reopened', function () {
    [$owner, $competition] = mr_league(3);
    $service = app(MatchService::class);

    foreach (mr_matches($competition) as $match) {
        $service->enterResult($match->refresh(), $owner, ['score_a' => 1, 'score_b' => 0], true);
    }

    $competition->refresh();

    expect($competition->status)->toBe(CompetitionStatus::Finished)
        ->and($competition->firstStage()->status)->toBe(StageStatus::Finished)
        ->and($competition->competitors()->pluck('final_rank')->sort()->values()->all())->toBe([1, 2, 3]);

    $match = mr_matches($competition)->first();
    expect(fn () => $service->enterResult($match, $owner, ['score_a' => 5, 'score_b' => 5], true))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $service->clearResult($match, $owner))->toThrow(InvalidArgumentException::class);

    app(CompetitionService::class)->reopen($competition, $owner);

    expect($competition->refresh()->status)->toBe(CompetitionStatus::Running)
        ->and($competition->competitors()->whereNotNull('final_rank')->count())->toBe(0);

    $service->enterResult($match->refresh(), $owner, ['score_a' => 0, 'score_b' => 1], true);
    expect($competition->refresh()->status)->toBe(CompetitionStatus::Finished);
});

it('does not wait for cancelled matches when finishing', function () {
    [$owner, $competition] = mr_league(3);
    $service = app(MatchService::class);
    $matches = mr_matches($competition);

    $service->cancel($matches[0], $owner);
    $service->enterResult($matches[1]->refresh(), $owner, ['score_a' => 1, 'score_b' => 0], true);
    $service->enterResult($matches[2]->refresh(), $owner, ['score_a' => 1, 'score_b' => 0], true);

    expect($competition->refresh()->status)->toBe(CompetitionStatus::Finished)
        ->and(fn () => $service->enterResult($matches[0]->refresh(), $owner, ['score_a' => 1, 'score_b' => 0], true))->toThrow(InvalidArgumentException::class);
});

it('validates game by game results and applies score based table points', function () {
    [$owner, $competition] = mr_league(3, 'squash_league');
    $service = app(MatchService::class);
    $match = mr_matches($competition)->first();

    expect(fn () => $service->enterResult($match, $owner, ['games' => [[11, 5], [11, 5]]], true))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $service->enterResult($match, $owner, ['games' => [[11, 10], [11, 5], [11, 5]]], true))->toThrow(InvalidArgumentException::class);

    $result = $service->enterResult($match, $owner, ['games' => [[11, 9], [9, 11], [11, 9], [8, 11], [12, 10]]], true);

    expect([$result->score_a, $result->score_b])->toBe([3, 2])
        ->and([$result->points_a, $result->points_b])->toBe([51, 50])
        ->and($result->games)->toHaveCount(5);

    $rows = collect(app(StandingsService::class)->forStage($competition->firstStage()));

    expect($rows->firstWhere('competitor_id', (int) $match->a_id)['points'])->toBe(2)
        ->and($rows->firstWhere('competitor_id', (int) $match->b_id)['points'])->toBe(1);

    // ثبت دوباره، گیم‌های قبلی را جایگزین می‌کند
    $again = $service->enterResult($match->refresh(), $owner, ['games' => [[11, 1], [11, 1], [11, 1]]], true);
    expect($again->games()->count())->toBe(3);
});

it('imports results by round and names in either orientation and reports bad lines', function () {
    [$owner, $competition] = mr_league();
    $matches = mr_matches($competition)->load(['a', 'b']);
    $one = $matches[0];
    $two = $matches->first(fn ($m) => $m->round !== $one->round);

    $text = "{$one->round}, {$one->a->name}, {$one->b->name}, 3, 1\n"
        ."{$two->round}، {$two->b->name}، {$two->a->name}، 0، 2\n"
        ."99, x, y, 1, 1\n"
        .'garbage';

    $result = app(MatchService::class)->importResults($competition, $owner, $text, true);

    expect($result['applied'])->toBe(2)
        ->and($result['errors'])->toHaveCount(2)
        ->and(array_column($result['errors'], 'line'))->toBe([3, 4])
        ->and([$one->refresh()->score_a, $one->score_b])->toBe([3, 1])
        ->and([$two->refresh()->score_a, $two->score_b])->toBe([2, 0]);
});

it('refuses result import for game based sports', function () {
    [$owner, $competition] = mr_league(3, 'squash_league');

    expect(fn () => app(MatchService::class)->importResults($competition, $owner, '1, a, b, 3, 0', true))->toThrow(InvalidArgumentException::class);
});

it('rejects schedule clashes for a team and for a court across the event', function () {
    [$owner, $competition] = mr_league();
    $service = app(MatchService::class);
    $matches = mr_matches($competition);
    $first = $matches[0];
    $ids = [$first->a_id, $first->b_id];

    $sameTeam = $matches->first(fn ($m) => $m->id !== $first->id && (in_array($m->a_id, $ids) || in_array($m->b_id, $ids)));
    $disjoint = $matches->first(fn ($m) => ! in_array($m->a_id, $ids) && ! in_array($m->b_id, $ids));
    $at = Carbon::parse('2026-12-01 10:00');

    $service->schedule($first, $owner, $at, 'زمین ۱');

    expect(fn () => $service->schedule($sameTeam, $owner, $at->copy()->addMinutes(30), null))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $service->schedule($disjoint, $owner, $at->copy()->addMinutes(10), 'زمین ۱'))->toThrow(InvalidArgumentException::class);

    $service->schedule($sameTeam, $owner, $at->copy()->addMinutes(90), null);
    $service->schedule($disjoint, $owner, $at->copy()->addMinutes(10), 'زمین ۲');

    // ورزش دوم در همان رویداد: زمین مشترک است
    $event = $competition->event;
    $other = cp_competition($event, $owner, 'school_league', 'بسکتبال');
    cp_fill($other, $owner, 3);
    cp_draw($other, $owner);
    $foreign = mr_matches($other)->first();

    expect(fn () => $service->schedule($foreign, $owner, $at->copy()->addMinutes(20), 'زمین ۱'))->toThrow(InvalidArgumentException::class);

    $service->schedule($foreign, $owner, $at->copy()->addMinutes(20), 'زمین ۳');

    expect($foreign->refresh()->court)->toBe('زمین ۳');
});

it('postpones and reschedules a match', function () {
    [$owner, $competition] = mr_league();
    $service = app(MatchService::class);
    $match = mr_matches($competition)->first();

    $postponed = $service->postpone($match, $owner);
    expect($postponed->status->value)->toBe('postponed');

    $back = $service->schedule($postponed, $owner, Carbon::parse('2026-12-02 09:00'), 'زمین ۱');
    expect($back->status->value)->toBe('scheduled')->and($back->scheduled_at->format('H:i'))->toBe('09:00');
});

it('adds and deletes manual matches but never regular ones', function () {
    [$owner, $competition] = mr_league();
    $service = app(MatchService::class);
    $stage = $competition->firstStage();
    [$a, $b] = $competition->competitors()->limit(2)->pluck('id')->all();

    $manual = $service->addManual($stage, $owner, ['a_id' => $a, 'b_id' => $b, 'round' => 99]);

    expect($manual->is_manual)->toBeTrue()->and($manual->number)->toBe(1)
        ->and(fn () => $service->addManual($stage, $owner, ['a_id' => $a, 'b_id' => $a, 'round' => 1]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $service->addManual($stage, $owner, ['a_id' => $a, 'b_id' => 999999, 'round' => 1]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $service->deleteManual(mr_matches($competition)->first(), $owner))->toThrow(InvalidArgumentException::class);

    $service->deleteManual($manual, $owner);

    expect(SportMatch::query()->whereKey($manual->id)->exists())->toBeFalse();
});

it('keeps played results and hands unplayed matches to the opponents when a team withdraws', function () {
    [$owner, $competition] = mr_league();
    $service = app(MatchService::class);
    $played = mr_matches($competition)->first();
    $service->enterResult($played, $owner, ['score_a' => 2, 'score_b' => 0], true);

    $leaver = Competitor::query()->findOrFail($played->a_id);
    app(RegistrationService::class)->withdraw($leaver, $owner);

    $theirs = mr_matches($competition)->filter(fn ($m) => $m->a_id === $leaver->id || $m->b_id === $leaver->id);

    expect($theirs)->toHaveCount(3);

    $walkovers = $theirs->filter(fn ($m) => $m->status->value === 'walkover');
    $kept = $theirs->first(fn ($m) => $m->id === $played->id);

    expect($walkovers)->toHaveCount(2)
        ->and($walkovers->every(fn ($m) => (int) $m->winner_id !== $leaver->id))->toBeTrue()
        ->and($kept->status->value)->toBe('finished')
        ->and($kept->counts_in_standings)->toBeTrue();
});

it('voids every result of a withdrawn team with the void_all policy', function () {
    [$owner, $competition] = mr_league();
    $stage = $competition->firstStage();
    $stage->update(['config' => ['withdrawal_policy' => 'void_all'] + ($stage->config ?? [])]);

    $service = app(MatchService::class);
    $played = mr_matches($competition)->first();
    $service->enterResult($played, $owner, ['score_a' => 2, 'score_b' => 0], true);

    $leaver = Competitor::query()->findOrFail($played->a_id);
    app(RegistrationService::class)->withdraw($leaver, $owner);

    $theirs = mr_matches($competition)->filter(fn ($m) => $m->a_id === $leaver->id || $m->b_id === $leaver->id);

    expect($theirs->every(fn ($m) => ! $m->counts_in_standings))->toBeTrue()
        ->and($theirs->filter(fn ($m) => $m->status->value === 'cancelled'))->toHaveCount(2);

    $rows = collect(app(StandingsService::class)->forStage($stage->refresh()));
    expect($rows->firstWhere('competitor_id', $leaver->id)['played'])->toBe(0);
});
