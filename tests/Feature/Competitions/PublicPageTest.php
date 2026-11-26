<?php

require_once __DIR__.'/../../Support/RankedTestHelpers.php';

use App\Models\Competition;
use App\Models\Competitor;
use App\Services\CompetitionService;
use App\Services\MatchService;
use App\Services\PublicLinkService;

beforeEach(fn () => $this->withoutVite());

/** لیگ ۴ تیمی آماده‌ی ثبت نتیجه + لینک عمومی فعال. */
function pp_league(bool $enable = true): array
{
    $owner = cp_owner();
    $event = tk_event($owner);
    $competition = cp_competition($event, $owner);
    cp_fill($competition, $owner, 4);
    cp_draw($competition, $owner);
    app(CompetitionService::class)->markReady($competition->refresh(), $owner);

    if ($enable) {
        $competition = app(PublicLinkService::class)->enable($competition->refresh(), $owner);
    }

    return [$owner, $event, $competition->refresh()];
}

function pp_url(Competition $competition): string
{
    return route('competitions.public', ['token' => $competition->public_token]);
}

it('serves a public page without logging in', function () {
    [, $event, $competition] = pp_league();

    $this->get(pp_url($competition))
        ->assertOk()
        ->assertSee($competition->name)
        ->assertSee($event->title)
        ->assertSee('تیم 1');
});

it('sends privacy headers and a noindex meta tag', function () {
    [, , $competition] = pp_league();

    $response = $this->get(pp_url($competition));

    $response->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertSee('noindex', false);

    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('returns 404 for unknown, malformed and disabled tokens alike', function () {
    [$owner, , $competition] = pp_league();

    $this->get(route('competitions.public', ['token' => str_repeat('a', 40)]))->assertNotFound();
    $this->get('/c/short')->assertNotFound();
    $this->get('/c/'.str_repeat('a', 40).'%20x')->assertNotFound();

    $token = $competition->public_token;
    app(PublicLinkService::class)->disable($competition, $owner);

    $this->get(route('competitions.public', ['token' => $token]))->assertNotFound();
    expect($competition->refresh()->public_token)->toBeNull();
});

it('invalidates the old link when the token is rotated', function () {
    [$owner, , $competition] = pp_league();
    $old = $competition->public_token;

    $rotated = app(PublicLinkService::class)->rotate($competition, $owner);

    expect($rotated->public_token)->not->toBe($old)->and(strlen($rotated->public_token))->toBe(40);

    $this->get(route('competitions.public', ['token' => $old]))->assertNotFound();
    $this->get(pp_url($rotated))->assertOk();
});

it('keeps the same token when enabling twice', function () {
    [$owner, , $competition] = pp_league();

    expect(app(PublicLinkService::class)->enable($competition, $owner)->public_token)->toBe($competition->public_token);
});

it('returns 404 after the competition is deleted', function () {
    [$owner, , $competition] = pp_league();
    $url = pp_url($competition);

    app(CompetitionService::class)->delete($competition, $owner);

    $this->get($url)->assertNotFound();
});

it('shows only confirmed results and standings', function () {
    [$owner, , $competition] = pp_league();
    $service = app(MatchService::class);
    $matches = $competition->firstStage()->matches()->orderBy('id')->get();

    $confirmed = $service->enterResult($matches[0], $owner, ['score_a' => 3, 'score_b' => 1], true);
    $service->enterResult($matches[1], $owner, ['score_a' => 7, 'score_b' => 5], false); // تأییدنشده

    $page = $this->get(pp_url($competition))->assertOk();

    $page->assertSee('۳ : ۱', false)
        ->assertDontSee('۷ : ۵', false)
        ->assertSee(__('publicpage.standings'));
    expect($confirmed->result_status->value)->toBe('confirmed');
});

it('never leaks contact details, documents or members', function () {
    [$owner, , $competition] = pp_league();

    $competitor = $competition->competitors()->first();
    $competitor->update([
        'documents' => ['id_card' => 'SECRET-DOC-NUMBER'],
        'meta' => ['phone' => '09120000000'],
    ]);

    $html = $this->get(pp_url($competition))->assertOk()->getContent();

    expect($html)->not->toContain('SECRET-DOC-NUMBER')
        ->and($html)->not->toContain('09120000000')
        ->and($html)->not->toContain($owner->name)
        ->and($html)->not->toContain('wire:');
});

it('abbreviates individual names when the option is on', function () {
    [$owner, , $competition] = rk_setup('ranked_final', 4);
    rk_draw($competition, $owner);
    $links = app(PublicLinkService::class);
    $competition = $links->enable($competition->refresh(), $owner);
    $competition->competitors()->first()->update(['name' => 'علی رضایی']);

    $this->get(pp_url($competition))->assertOk()->assertSee('علی رضایی');

    $links->setAbbreviateNames($competition, $owner, true);

    $this->get(pp_url($competition))->assertOk()
        ->assertDontSee('علی رضایی')
        ->assertSee('ع. ر.');
});

it('keeps team names untouched even when abbreviation is on', function () {
    [$owner, , $competition] = pp_league();
    app(PublicLinkService::class)->setAbbreviateNames($competition, $owner, true);

    $this->get(pp_url($competition))->assertOk()->assertSee('تیم 1');
});

it('shows ranked heats with values only for confirmed heats', function () {
    [$owner, , $competition] = rk_setup();
    rk_draw($competition, $owner);
    $competition = app(PublicLinkService::class)->enable($competition->refresh(), $owner);
    [$first, $second] = rk_heats($competition)->all();

    rk_enter($first, $owner, [10.11, 10.22, 10.33, 10.44, 10.55]);
    rk_enter($second, $owner, [11.11, 11.22, 11.33, 11.44, 11.55], canConfirm: false);

    $this->get(pp_url($competition))->assertOk()
        ->assertSee('۱۰.۱۱', false)
        ->assertDontSee('۱۱.۱۱', false)
        ->assertSee(__('publicpage.pending_heat'));
});

it('shows the final ranking once a ranked competition finishes', function () {
    [$owner, , $competition] = rk_setup('ranked_final', 3);
    rk_draw($competition, $owner);
    $competition = app(PublicLinkService::class)->enable($competition->refresh(), $owner);

    rk_enter(rk_heats($competition)->first(), $owner, [10, 11, 12]);

    $this->get(pp_url($competition))->assertOk()->assertSee(__('publicpage.final_ranking'));
});

it('does not show hidden competitors who withdrew in the final ranking', function () {
    [$owner, , $competition] = rk_setup('ranked_final', 3);
    rk_draw($competition, $owner);
    $competition = app(PublicLinkService::class)->enable($competition->refresh(), $owner);
    $withdrawn = rk_heats($competition)->first()->results->last()->competitor;

    rk_enter(rk_heats($competition)->first(), $owner, [10, 11, 12]);
    Competitor::query()->whereKey($withdrawn->id)->update(['status' => 'withdrawn']);

    $html = $this->get(pp_url($competition))->assertOk()->getContent();
    $section = substr($html, (int) strpos($html, __('publicpage.final_ranking')));

    expect(substr_count($section, $withdrawn->name))->toBeLessThanOrEqual(1);
});

it('rate limits the public endpoint', function () {
    $token = str_repeat('b', 40);

    foreach (range(1, 60) as $i) {
        $this->get(route('competitions.public', ['token' => $token]))->assertNotFound();
    }

    $this->get(route('competitions.public', ['token' => $token]))->assertStatus(429);
});
