<?php

require_once __DIR__.'/../../Support/CompetitionTestHelpers.php';

use App\Enums\CompetitionRole;
use App\Enums\CompetitionStatus;
use App\Enums\EventRole;
use App\Enums\StageStatus;
use App\Models\ActivityLog;
use App\Models\Competition;
use App\Models\Competitor;
use App\Models\SportMatch;
use App\Models\User;
use App\Services\CompetitionService;
use App\Services\DrawService;
use App\Services\RegistrationService;
use App\Services\StandingsService;

it('creates a competition from a template with its stages and scoring config', function () {
    $owner = cp_owner();
    $event = tk_event($owner);

    $competition = cp_competition($event, $owner, 'league_2legs', 'لیگ فوتسال');

    expect($competition->status)->toBe(CompetitionStatus::Registration)
        ->and($competition->stages)->toHaveCount(1)
        ->and($competition->stages->first()->config('legs'))->toBe(2)
        ->and($competition->config('unit'))->toBe('goals')
        ->and($competition->config('table_points.win'))->toBe(3)
        ->and(ActivityLog::query()->where('action', 'competition.created')->where('event_id', $event->id)->exists())->toBeTrue();
});

it('ships the seeded templates', function () {
    $codes = app(CompetitionService::class)->templates()->pluck('code')->all();

    expect($codes)->toContain('school_league', 'league_2legs', 'squash_league');
});

it('blocks individual accounts from using the module', function () {
    $owner = User::factory()->individual()->create();
    $event = tk_event($owner);

    expect(fn () => cp_competition($event, $owner))->toThrow(InvalidArgumentException::class);
    expect(Competition::query()->count())->toBe(0);
});

it('keeps archived events read only', function () {
    $owner = cp_owner();
    $event = tk_event($owner);
    $competition = cp_competition($event, $owner);

    $event->update(['status' => 'archived']);

    expect(fn () => cp_competition($event->refresh(), $owner, 'school_league', 'دیگر'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(RegistrationService::class)->register($competition->refresh(), $owner, ['name' => 'تیم']))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects unknown templates and empty names', function () {
    $owner = cp_owner();
    $event = tk_event($owner);
    $service = app(CompetitionService::class);

    expect(fn () => $service->create($event, $owner, ['template_code' => 'nope', 'name' => 'x']))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $service->create($event, $owner, ['template_code' => 'school_league', 'name' => '  ']))->toThrow(InvalidArgumentException::class);
});

it('registers competitors with unique names and honours capacity', function () {
    $owner = cp_owner();
    $competition = cp_competition(tk_event($owner), $owner);
    $registration = app(RegistrationService::class);

    app(CompetitionService::class)->update($competition, $owner, ['capacity' => 2]);

    $registration->register($competition->refresh(), $owner, ['name' => 'تیم الف', 'members' => ['علی', 'رضا']]);

    expect(fn () => $registration->register($competition, $owner, ['name' => 'تیم الف']))->toThrow(InvalidArgumentException::class);

    $registration->register($competition, $owner, ['name' => 'تیم ب']);

    expect(fn () => $registration->register($competition, $owner, ['name' => 'تیم ج']))->toThrow(InvalidArgumentException::class)
        ->and(Competitor::query()->where('name', 'تیم الف')->first()->members)->toHaveCount(2);
});

it('does not count withdrawn teams against capacity or uniqueness', function () {
    $owner = cp_owner();
    $competition = cp_competition(tk_event($owner), $owner);
    $registration = app(RegistrationService::class);
    app(CompetitionService::class)->update($competition, $owner, ['capacity' => 2]);

    $a = $registration->register($competition->refresh(), $owner, ['name' => 'الف']);
    $registration->register($competition, $owner, ['name' => 'ب']);
    $registration->withdraw($a, $owner);

    $registration->register($competition, $owner, ['name' => 'الف']);

    expect($competition->competitors()->count())->toBe(3);
});

it('bulk registers lines and reports bad ones separately', function () {
    $owner = cp_owner();
    $competition = cp_competition(tk_event($owner), $owner);

    $result = app(RegistrationService::class)->bulkRegister($competition, $owner, "تیم ۱، مدرسه الف\nتیم ۲\n\nتیم ۱\n");

    expect($result['created'])->toBe(2)
        ->and($result['errors'])->toHaveCount(1)
        ->and($result['errors'][0]['line'])->toBe(4)
        ->and($competition->competitors()->where('name', 'تیم ۱')->first()->affiliation)->toBe('مدرسه الف');
});

it('auto confirms registrations when confirmation is not required', function () {
    $owner = cp_owner();
    $competition = cp_competition(tk_event($owner), $owner);
    app(CompetitionService::class)->update($competition, $owner, ['requires_confirmation' => false]);

    $c = app(RegistrationService::class)->register($competition->refresh(), $owner, ['name' => 'تیم']);

    expect($c->status->value)->toBe('confirmed');
});

it('only draws confirmed teams and needs at least two', function () {
    $owner = cp_owner();
    $competition = cp_competition(tk_event($owner), $owner);
    $registration = app(RegistrationService::class);
    $draw = app(DrawService::class);

    $a = $registration->register($competition, $owner, ['name' => 'الف']);
    $registration->register($competition, $owner, ['name' => 'ب']);
    $registration->confirm($a, $owner);

    expect(fn () => $draw->preview($competition->refresh(), 'random', 's'))->toThrow(InvalidArgumentException::class);
});

it('previews a draw reproducibly and stores nothing', function () {
    $owner = cp_owner();
    $competition = cp_competition(tk_event($owner), $owner);
    cp_fill($competition, $owner, 8);
    $draw = app(DrawService::class);

    $one = $draw->preview($competition->refresh(), 'random', 'same');
    $two = $draw->preview($competition, 'random', 'same');

    expect($one['order'])->toBe($two['order'])
        ->and($one['order'])->toHaveCount(8)
        ->and($competition->firstStage()->entries()->count())->toBe(0);
});

it('confirms the draw with an audit document and moves the status', function () {
    $owner = cp_owner();
    $competition = cp_competition(tk_event($owner), $owner);
    cp_fill($competition, $owner, 6);
    $draw = app(DrawService::class);

    $preview = $draw->preview($competition->refresh(), 'random', 'audit-seed');
    $stage = $draw->confirm($competition, $owner, 'random', 'audit-seed');

    expect($stage->status)->toBe(StageStatus::Drawn)
        ->and($competition->refresh()->status)->toBe(CompetitionStatus::Drawn)
        ->and($stage->entries()->pluck('competitor_id')->map(fn ($i) => (int) $i)->all())->toBe($preview['order'])
        ->and($stage->entries()->pluck('draw_position')->map(fn ($i) => (int) $i)->all())->toBe(range(1, 6))
        ->and($stage->draws()->first()->rng_seed)->toBe('audit-seed')
        ->and($stage->draws()->first()->performed_by)->toBe($owner->id)
        ->and($stage->config('lots_seed'))->not->toBeEmpty();

    expect(fn () => $draw->confirm($competition->refresh(), $owner, 'random', 'again'))->toThrow(InvalidArgumentException::class);
    expect(fn () => app(RegistrationService::class)->register($competition, $owner, ['name' => 'دیرهنگام']))->toThrow(InvalidArgumentException::class);
});

it('rejects withdrawal after the draw until the result module handles it', function () {
    $owner = cp_owner();
    $competition = cp_competition(tk_event($owner), $owner);
    cp_fill($competition, $owner, 4);
    cp_draw($competition, $owner);

    $competitor = $competition->competitors()->first();

    expect(fn () => app(RegistrationService::class)->withdraw($competitor, $owner))->toThrow(InvalidArgumentException::class);
});

it('generates 210 matches for 15 teams in two legs and can be marked ready', function () {
    $owner = cp_owner();
    $competition = cp_competition(tk_event($owner), $owner, 'league_2legs', 'لیگ');
    cp_fill($competition, $owner, 15);

    expect(cp_draw($competition, $owner))->toBe(210);

    $stage = $competition->firstStage();
    $matches = $stage->matches()->get();

    expect($matches)->toHaveCount(210)
        ->and($matches->pluck('round')->max())->toBe(30)
        ->and($matches->map(fn ($m) => $m->a_id.'-'.$m->b_id)->unique())->toHaveCount(210)
        ->and($matches->every(fn ($m) => $m->status->value === 'scheduled' && $m->counts_in_standings))->toBeTrue();

    $ready = app(CompetitionService::class)->markReady($competition->refresh(), $owner);

    expect($ready->status)->toBe(CompetitionStatus::Ready);
});

it('refuses to build fixtures twice or before the draw', function () {
    $owner = cp_owner();
    $competition = cp_competition(tk_event($owner), $owner);
    cp_fill($competition, $owner, 4);
    $draw = app(DrawService::class);

    expect(fn () => $draw->generateFixtures($competition->refresh(), $owner))->toThrow(InvalidArgumentException::class);

    cp_draw($competition, $owner);

    expect(fn () => $draw->generateFixtures($competition->refresh(), $owner))->toThrow(InvalidArgumentException::class);
});

it('cannot mark ready without fixtures', function () {
    $owner = cp_owner();
    $competition = cp_competition(tk_event($owner), $owner);
    cp_fill($competition, $owner, 4);
    app(DrawService::class)->confirm($competition->refresh(), $owner, 'random', 'x');

    expect(fn () => app(CompetitionService::class)->markReady($competition->refresh(), $owner))->toThrow(InvalidArgumentException::class);
});

it('resets the draw and fixtures before any confirmed result', function () {
    $owner = cp_owner();
    $competition = cp_competition(tk_event($owner), $owner);
    cp_fill($competition, $owner, 5);
    cp_draw($competition, $owner);

    app(DrawService::class)->reset($competition->refresh(), $owner);

    $stage = $competition->firstStage();

    expect($competition->refresh()->status)->toBe(CompetitionStatus::Registration)
        ->and($stage->status)->toBe(StageStatus::Pending)
        ->and($stage->matches()->count())->toBe(0)
        ->and($stage->entries()->count())->toBe(0)
        ->and($stage->draws()->count())->toBe(0);

    // دوباره قابل قرعه است
    expect(cp_draw($competition, $owner, 'second'))->toBe(10);
});

it('locks the draw once a result is confirmed', function () {
    $owner = cp_owner();
    $competition = cp_competition(tk_event($owner), $owner);
    cp_fill($competition, $owner, 4);
    cp_draw($competition, $owner);

    $match = $competition->firstStage()->matches()->first();
    $match->update([
        'status' => 'finished', 'result_status' => 'confirmed',
        'score_a' => 1, 'score_b' => 0, 'winner_id' => $match->a_id,
    ]);

    expect($competition->refresh()->hasConfirmedResults())->toBeTrue()
        ->and(fn () => app(DrawService::class)->reset($competition, $owner))->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(CompetitionService::class)->delete($competition, $owner))->toThrow(InvalidArgumentException::class);
});

it('computes standings from confirmed counted matches only', function () {
    $owner = cp_owner();
    $competition = cp_competition(tk_event($owner), $owner);
    cp_fill($competition, $owner, 3);
    cp_draw($competition, $owner);

    $stage = $competition->firstStage();
    $matches = $stage->matches()->orderBy('id')->get();

    // ۱: تأییدشده و شمرده‌شده — ۲: فقط ثبت‌شده — ۳: تأییدشده ولی خارج از رده‌بندی
    $matches[0]->update(['status' => 'finished', 'result_status' => 'confirmed', 'score_a' => 2, 'score_b' => 0, 'winner_id' => $matches[0]->a_id]);
    $matches[1]->update(['status' => 'finished', 'result_status' => 'entered', 'score_a' => 5, 'score_b' => 0, 'winner_id' => $matches[1]->a_id]);
    $matches[2]->update(['status' => 'finished', 'result_status' => 'confirmed', 'counts_in_standings' => false, 'score_a' => 4, 'score_b' => 0, 'winner_id' => $matches[2]->a_id]);

    $rows = app(StandingsService::class)->forStage($stage->refresh());

    expect($rows)->toHaveCount(3)
        ->and($rows[0]['competitor_id'])->toBe((int) $matches[0]->a_id)
        ->and($rows[0]['points'])->toBe(3)
        ->and($rows[0]['played'])->toBe(1)
        ->and(array_sum(array_column($rows, 'played')))->toBe(2)
        ->and($rows[0]['name'])->not->toBe('—');
});

it('assigns competition roles only to non-viewer event members', function () {
    $owner = cp_owner();
    $event = tk_event($owner);
    $competition = cp_competition($event, $owner);
    $service = app(CompetitionService::class);

    $scorer = User::factory()->create();
    $viewer = User::factory()->create();
    $outsider = User::factory()->create();
    tk_join($event, $scorer);
    tk_join($event, $viewer, EventRole::Viewer);

    $service->assignRole($competition, $owner, $scorer, CompetitionRole::Scorer);
    $service->assignRole($competition, $owner, $scorer, CompetitionRole::Manager);

    expect($competition->members()->count())->toBe(1)
        ->and($competition->members()->first()->pivot->role)->toBe('manager')
        ->and(fn () => $service->assignRole($competition, $owner, $viewer, CompetitionRole::Scorer))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $service->assignRole($competition, $owner, $outsider, CompetitionRole::Scorer))->toThrow(InvalidArgumentException::class);

    $service->removeRole($competition, $owner, $scorer);
    expect($competition->members()->count())->toBe(0);
});

it('writes an activity log for the draw and fixtures', function () {
    $owner = cp_owner();
    $event = tk_event($owner);
    $competition = cp_competition($event, $owner);
    cp_fill($competition, $owner, 4);
    cp_draw($competition, $owner);

    foreach (['competition.drawn', 'competition.fixtures_generated', 'competitor.registered'] as $action) {
        expect(ActivityLog::query()->where('action', $action)->where('event_id', $event->id)->exists())->toBeTrue();
    }
});

it('never touches matches of another competition when resetting', function () {
    $owner = cp_owner();
    $event = tk_event($owner);
    $a = cp_competition($event, $owner, 'school_league', 'الف');
    $b = cp_competition($event, $owner, 'school_league', 'ب');
    cp_fill($a, $owner, 4);
    cp_fill($b, $owner, 4);
    cp_draw($a, $owner);
    cp_draw($b, $owner);

    app(DrawService::class)->reset($a->refresh(), $owner);

    expect(SportMatch::query()->count())->toBe(6)
        ->and($b->firstStage()->matches()->count())->toBe(6);
});
