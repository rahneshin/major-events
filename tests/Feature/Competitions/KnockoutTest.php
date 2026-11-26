<?php

require_once __DIR__.'/../../Support/CompetitionTestHelpers.php';

use App\Enums\CompetitionStatus;
use App\Enums\MatchStatus;
use App\Enums\StageStatus;
use App\Livewire\Competitions\CompetitionShow;
use App\Models\Competition;
use App\Models\Competitor;
use App\Models\SportMatch;
use App\Models\User;
use App\Services\CompetitionService;
use App\Services\DrawService;
use App\Services\MatchService;
use App\Services\RegistrationService;
use Livewire\Livewire;

/**
 * سید i = «تیم i»؛ قرعه‌ی seeded ← جایگاه قرعه = بذر.
 *
 * @param  array<string, mixed>  $options
 * @return array{0: User, 1: Competition}
 */
function kn_setup(string $template, int $teams, array $options = []): array
{
    $owner = cp_owner();
    $competition = cp_competition(tk_event($owner), $owner, $template);
    $registration = app(RegistrationService::class);

    foreach (range(1, $teams) as $i) {
        $competitor = $registration->register($competition, $owner, ['name' => 'تیم '.$i, 'seed' => $i]);
        $registration->confirm($competitor, $owner);
    }

    $draw = app(DrawService::class);
    $draw->confirm($competition->refresh(), $owner, 'seeded', 'kn-seed', $options);
    $draw->generateFixtures($competition->refresh(), $owner);
    app(CompetitionService::class)->markReady($competition->refresh(), $owner);

    return [$owner, $competition->refresh()];
}

function kn_id(Competition $competition, int $team): int
{
    return (int) Competitor::query()->where('competition_id', $competition->id)->where('name', 'تیم '.$team)->value('id');
}

function kn_match(Competition $competition, int $round, int $number, string $bracket = 'winners'): SportMatch
{
    return SportMatch::query()
        ->whereIn('stage_id', $competition->stages()->pluck('id'))
        ->where('round', $round)->where('number', $number)->where('bracket', $bracket)
        ->firstOrFail();
}

/** @param array<string, mixed> $extra */
function kn_play(Competition $competition, User $owner, int $round, int $number, int $a, int $b, array $extra = [], string $bracket = 'winners'): SportMatch
{
    return app(MatchService::class)->enterResult(
        kn_match($competition, $round, $number, $bracket),
        $owner,
        ['score_a' => $a, 'score_b' => $b] + $extra,
        true,
    );
}

it('builds and plays an 8-team knockout to a champion with final ranks', function () {
    [$owner, $competition] = kn_setup('quick_knockout', 8);

    expect($competition->firstStage()->matches()->count())->toBe(7);

    $first = kn_match($competition, 1, 1);
    expect($first->a_id)->toBe(kn_id($competition, 1))->and($first->b_id)->toBe(kn_id($competition, 8));

    foreach ([1, 2, 3, 4] as $n) {
        kn_play($competition, $owner, 1, $n, 1, 0);
    }

    $semi = kn_match($competition, 2, 1);
    expect($semi->a_id)->toBe(kn_id($competition, 1))->and($semi->b_id)->toBe(kn_id($competition, 4));

    kn_play($competition, $owner, 2, 1, 1, 0);
    kn_play($competition, $owner, 2, 2, 1, 0);

    expect($competition->refresh()->status)->toBe(CompetitionStatus::Running);

    kn_play($competition, $owner, 3, 1, 2, 1);

    expect($competition->refresh()->status)->toBe(CompetitionStatus::Finished);

    $rank = fn (int $team) => Competitor::query()->find(kn_id($competition, $team))->final_rank;

    expect($rank(1))->toBe(1)->and($rank(2))->toBe(2)
        ->and($rank(3))->toBe(3)->and($rank(4))->toBe(3)
        ->and($rank(5))->toBe(5)->and($rank(8))->toBe(5);
});

it('places top seeds directly into round two when the field is not a power of two', function () {
    [$owner, $competition] = kn_setup('quick_knockout', 5);

    expect($competition->firstStage()->matches()->count())->toBe(4);

    $semi1 = kn_match($competition, 2, 1);
    expect($semi1->a_id)->toBe(kn_id($competition, 1))->and($semi1->b_id)->toBeNull();

    $semi2 = kn_match($competition, 2, 2);
    expect($semi2->a_id)->toBe(kn_id($competition, 2))->and($semi2->b_id)->toBe(kn_id($competition, 3));

    kn_play($competition, $owner, 1, 1, 2, 1);

    expect(kn_match($competition, 2, 1)->b_id)->toBe(kn_id($competition, 4));
});

it('plays a third place match and ranks 3rd and 4th from it', function () {
    [$owner, $competition] = kn_setup('quick_knockout', 4, ['third_place' => true]);

    expect($competition->firstStage()->matches()->count())->toBe(4);

    kn_play($competition, $owner, 1, 1, 1, 0); // تیم ۱ تیم ۴ را می‌برد
    kn_play($competition, $owner, 1, 2, 0, 1); // تیم ۳ تیم ۲ را می‌برد

    $third = kn_match($competition, 2, 2, 'third_place');
    expect($third->a_id)->toBe(kn_id($competition, 4))->and($third->b_id)->toBe(kn_id($competition, 2));

    kn_play($competition, $owner, 2, 2, 0, 3, [], 'third_place'); // تیم ۲ سوم می‌شود
    kn_play($competition, $owner, 2, 1, 1, 0);

    expect($competition->refresh()->status)->toBe(CompetitionStatus::Finished);

    $rank = fn (int $team) => Competitor::query()->find(kn_id($competition, $team))->final_rank;
    expect($rank(1))->toBe(1)->and($rank(3))->toBe(2)->and($rank(2))->toBe(3)->and($rank(4))->toBe(4);
});

it('rejects a knockout tie unless a tiebreak winner is given', function () {
    [$owner, $competition] = kn_setup('quick_knockout', 4);

    expect(fn () => kn_play($competition, $owner, 1, 1, 1, 1))->toThrow(InvalidArgumentException::class);

    $match = kn_play($competition, $owner, 1, 1, 1, 1, ['tiebreak' => 'b']);

    expect($match->winner_id)->toBe(kn_id($competition, 4))
        ->and($match->result_meta)->toBe(['tiebreak' => 'b'])
        ->and(kn_match($competition, 2, 1)->a_id)->toBe(kn_id($competition, 4));
});

it('replaces the advanced competitor on edit and retracts on clear while downstream is empty', function () {
    [$owner, $competition] = kn_setup('quick_knockout', 4);
    $service = app(MatchService::class);

    kn_play($competition, $owner, 1, 1, 1, 0);
    expect(kn_match($competition, 2, 1)->a_id)->toBe(kn_id($competition, 1));

    kn_play($competition, $owner, 1, 1, 0, 2); // ویرایش: تیم ۴ برنده
    expect(kn_match($competition, 2, 1)->a_id)->toBe(kn_id($competition, 4));

    $service->clearResult(kn_match($competition, 1, 1), $owner);
    expect(kn_match($competition, 2, 1)->a_id)->toBeNull();
});

it('locks an advanced result once the next match has a result', function () {
    [$owner, $competition] = kn_setup('quick_knockout', 4, ['third_place' => true]);
    $service = app(MatchService::class);

    kn_play($competition, $owner, 1, 1, 1, 0);
    kn_play($competition, $owner, 1, 2, 1, 0);
    kn_play($competition, $owner, 2, 1, 1, 0); // فینال؛ ردهبندی هنوز مانده ← ورزش پایان نمی‌یابد

    expect($competition->refresh()->status)->toBe(CompetitionStatus::Running);

    expect(fn () => kn_play($competition, $owner, 1, 1, 0, 1))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $service->clearResult(kn_match($competition, 1, 1), $owner))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $service->walkover(kn_match($competition, 1, 1), $owner, 'b'))->toThrow(InvalidArgumentException::class);

    $service->clearResult(kn_match($competition, 2, 1), $owner);
    kn_play($competition, $owner, 1, 1, 0, 1);

    expect(kn_match($competition, 2, 1)->a_id)->toBe(kn_id($competition, 4))
        ->and(kn_match($competition, 2, 2, 'third_place')->a_id)->toBe(kn_id($competition, 1));
});

it('advances the winner of a walkover and refuses to cancel knockout matches', function () {
    [$owner, $competition] = kn_setup('quick_knockout', 4);
    $service = app(MatchService::class);

    $service->walkover(kn_match($competition, 1, 1), $owner, 'b');

    expect(kn_match($competition, 1, 1)->status)->toBe(MatchStatus::Walkover)
        ->and(kn_match($competition, 2, 1)->a_id)->toBe(kn_id($competition, 4))
        ->and(fn () => $service->cancel(kn_match($competition, 1, 2), $owner))->toThrow(InvalidArgumentException::class);
});

it('gives the opponent a walkover when a competitor withdraws', function () {
    [$owner, $competition] = kn_setup('quick_knockout', 4);

    app(RegistrationService::class)->withdraw(Competitor::query()->find(kn_id($competition, 4)), $owner);

    $semi = kn_match($competition, 1, 1);
    expect($semi->status)->toBe(MatchStatus::Walkover)
        ->and($semi->winner_id)->toBe(kn_id($competition, 1))
        ->and(kn_match($competition, 2, 1)->a_id)->toBe(kn_id($competition, 1));
});

it('settles a later match automatically when a withdrawn competitor is placed against someone', function () {
    [$owner, $competition] = kn_setup('quick_knockout', 8);

    kn_play($competition, $owner, 1, 1, 1, 0); // تیم ۱ به دور دوم می‌رود
    app(RegistrationService::class)->withdraw(Competitor::query()->find(kn_id($competition, 1)), $owner);

    // حریف هنوز معلوم نیست ← چیزی تغییر نمی‌کند
    expect(kn_match($competition, 2, 1)->status)->toBe(MatchStatus::Scheduled);

    kn_play($competition, $owner, 1, 2, 3, 0); // تیم ۴ می‌رسد ← برد اداری خودکار

    $semi = kn_match($competition, 2, 1);
    expect($semi->status)->toBe(MatchStatus::Walkover)
        ->and($semi->winner_id)->toBe(kn_id($competition, 4))
        ->and(kn_match($competition, 3, 1)->a_id)->toBe(kn_id($competition, 4));
});

it('validates group draw options', function () {
    $owner = cp_owner();
    $competition = cp_competition(tk_event($owner), $owner, 'group_knockout');
    cp_fill($competition, $owner, 8);
    $draw = app(DrawService::class);

    expect(fn () => $draw->preview($competition->refresh(), 'seeded', 's', ['groups' => 5, 'advance' => 1]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $draw->preview($competition->refresh(), 'seeded', 's', ['groups' => 2, 'advance' => 4]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $draw->preview($competition->refresh(), 'seeded', 's', []))->toThrow(InvalidArgumentException::class);

    $preview = $draw->preview($competition->refresh(), 'seeded', 's', ['groups' => 2, 'advance' => 2]);
    expect(array_keys($preview['groups']))->toBe(['A', 'B'])->and($preview['groups']['A'])->toHaveCount(4);
});

/** همه‌ی بازی‌های گروهی به‌جز n تای آخر؛ تیم با شماره‌ی کمتر (id کمتر) برنده. */
function kn_play_groups(Competition $competition, User $owner, int $leave = 0): void
{
    $matches = $competition->firstStage()->matches()->orderBy('id')->get();

    foreach ($matches->take($matches->count() - $leave) as $m) {
        app(MatchService::class)->enterResult($m, $owner, [
            'score_a' => $m->a_id < $m->b_id ? 1 : 0,
            'score_b' => $m->a_id < $m->b_id ? 0 : 1,
        ], true);
    }
}

it('runs groups into a knockout automatically and finishes with final ranks', function () {
    [$owner, $competition] = kn_setup('group_knockout', 8, ['groups' => 2, 'advance' => 2]);
    [$groups, $knockout] = $competition->stages->all();

    expect($groups->matches()->count())->toBe(12)
        ->and($knockout->matches()->count())->toBe(3)
        ->and($knockout->refresh()->status)->toBe(StageStatus::Pending)
        ->and($groups->entries()->where('group_name', 'A')->count())->toBe(4);

    $semi = kn_match($competition, 1, 1);
    expect($semi->a_id)->toBeNull()->and($semi->a_source['group_rank'])->toBe(['A', 1]);

    kn_play_groups($competition, $owner, 1);

    expect($knockout->refresh()->status)->toBe(StageStatus::Pending)
        ->and(kn_match($competition, 1, 1)->a_id)->toBeNull();

    // آخرین بازی گروهی ← گروه‌ها پایان می‌یابد و جدول حذفی پر می‌شود
    $last = $groups->matches()->whereNull('result_status')->firstOrFail();
    app(MatchService::class)->enterResult($last, $owner, [
        'score_a' => $last->a_id < $last->b_id ? 1 : 0,
        'score_b' => $last->a_id < $last->b_id ? 0 : 1,
    ], true);

    expect($groups->refresh()->status)->toBe(StageStatus::Finished)
        ->and($knockout->refresh()->status)->toBe(StageStatus::Drawn);

    // گروه A = تیم‌های ۱،۴،۵،۸ و گروه B = ۲،۳،۶،۷؛ صعود: A1=۱، A2=۴، B1=۲، B2=۳
    // بذرها: ۱=A1 ۲=B1 ۳=A2 ۴=B2 ← نیمه‌نهایی‌ها ۱–۳ و ۲–۴
    $s1 = kn_match($competition, 1, 1);
    $s2 = kn_match($competition, 1, 2);
    expect([$s1->a_id, $s1->b_id])->toBe([kn_id($competition, 1), kn_id($competition, 3)])
        ->and([$s2->a_id, $s2->b_id])->toBe([kn_id($competition, 2), kn_id($competition, 4)]);

    kn_play($competition, $owner, 1, 1, 1, 0);
    kn_play($competition, $owner, 1, 2, 1, 0);
    kn_play($competition, $owner, 2, 1, 2, 0);

    expect($competition->refresh()->status)->toBe(CompetitionStatus::Finished);

    $rank = fn (int $team) => Competitor::query()->find(kn_id($competition, $team))->final_rank;
    expect($rank(1))->toBe(1)->and($rank(2))->toBe(2)->and($rank(3))->toBe(3)->and($rank(4))->toBe(3);
});

it('reopens the bracket when a group result is cleared and locks groups once knockout has results', function () {
    [$owner, $competition] = kn_setup('group_knockout', 8, ['groups' => 2, 'advance' => 2]);
    [$groups, $knockout] = $competition->stages->all();
    $service = app(MatchService::class);

    kn_play_groups($competition, $owner);

    expect($knockout->refresh()->status)->toBe(StageStatus::Drawn);

    $any = $groups->matches()->orderBy('id')->first();
    $service->clearResult($any, $owner);

    expect($groups->refresh()->status)->toBe(StageStatus::Running)
        ->and($knockout->refresh()->status)->toBe(StageStatus::Pending)
        ->and(kn_match($competition, 1, 1)->a_id)->toBeNull();

    $service->enterResult($any->refresh(), $owner, [
        'score_a' => $any->a_id < $any->b_id ? 1 : 0,
        'score_b' => $any->a_id < $any->b_id ? 0 : 1,
    ], true);

    expect($knockout->refresh()->status)->toBe(StageStatus::Drawn);

    kn_play($competition, $owner, 1, 1, 1, 0);

    expect(fn () => $service->clearResult($any->refresh(), $owner))->toThrow(InvalidArgumentException::class);
});

it('resets a group-knockout draw including the bracket', function () {
    [$owner, $competition] = kn_setup('group_knockout', 8, ['groups' => 2, 'advance' => 2]);

    app(DrawService::class)->reset($competition->refresh(), $owner);

    expect(SportMatch::query()->whereIn('stage_id', $competition->stages()->pluck('id'))->count())->toBe(0)
        ->and($competition->refresh()->stages->every(fn ($s) => $s->status === StageStatus::Pending))->toBeTrue();
});

it('shows the bracket, stage tabs and group tables on the page', function () {
    [$owner, $competition] = kn_setup('group_knockout', 8, ['groups' => 2, 'advance' => 2]);

    $page = Livewire::actingAs($owner)->test(CompetitionShow::class, ['event' => $competition->event, 'competition' => $competition]);

    $page->call('setSection', 'standings')
        ->assertSee('گروه A')
        ->assertSee('گروه B');

    $knockout = $competition->stages->last();

    $page->call('setSection', 'fixtures')
        ->call('selectStage', (string) $knockout->id)
        ->assertSee('رتبه‌ی ۱ گروه A')
        ->call('setFixturesView', 'bracket')
        ->assertSee('نیمه‌نهایی')
        ->assertSee('فینال');
});
