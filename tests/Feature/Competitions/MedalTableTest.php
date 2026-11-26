<?php

require_once __DIR__.'/../../Support/CompetitionTestHelpers.php';

use App\Livewire\Events\EventMedals;
use App\Models\Competition;
use App\Models\Competitor;
use App\Models\Event;
use App\Models\User;
use App\Services\MedalTableService;
use Livewire\Livewire;

/**
 * ورزش پایان‌یافته‌ی ساختگی با رتبه‌های نهایی مشخص.
 *
 * @param  list<array{0: string, 1: string|null, 2: int|null, 3?: string}>  $rows  [نام، وابستگی، رتبه، وضعیت?]
 */
function mt_finished(Event $event, User $owner, string $name, array $rows, string $status = 'finished'): void
{
    $competition = cp_competition($event, $owner, 'school_league', $name);

    foreach ($rows as $row) {
        Competitor::query()->create([
            'competition_id' => $competition->id,
            'name' => $row[0],
            'affiliation' => $row[1],
            'final_rank' => $row[2],
            'status' => $row[3] ?? 'confirmed',
        ]);
    }

    $competition->update(['status' => $status]);
}

function mt_table(Event $event): array
{
    return app(MedalTableService::class)->forEvent($event->refresh());
}

it('counts medals per affiliation and orders by gold, silver, bronze', function () {
    $owner = cp_owner();
    $event = tk_event($owner);

    mt_finished($event, $owner, 'فوتسال', [['الف۱', 'مدرسه الف', 1], ['ب۱', 'مدرسه ب', 2], ['ج۱', 'مدرسه ج', 3]]);
    mt_finished($event, $owner, 'والیبال', [['ب۲', 'مدرسه ب', 1], ['الف۲', 'مدرسه الف', 2], ['ج۲', 'مدرسه ج', 3]]);
    mt_finished($event, $owner, 'بسکتبال', [['ب۳', 'مدرسه ب', 1], ['ج۳', 'مدرسه ج', 2], ['الف۳', 'مدرسه الف', 3]]);

    $table = mt_table($event);
    $rows = collect($table['rows']);

    expect($table['competitions'])->toBe(3)
        ->and($rows->pluck('affiliation')->all())->toBe(['مدرسه ب', 'مدرسه الف', 'مدرسه ج'])
        ->and($rows[0])->toMatchArray(['rank' => 1, 'gold' => 2, 'silver' => 1, 'bronze' => 0, 'total' => 3])
        ->and($rows[1])->toMatchArray(['rank' => 2, 'gold' => 1, 'silver' => 1, 'bronze' => 1])
        ->and($rows[2])->toMatchArray(['rank' => 3, 'gold' => 0, 'silver' => 1, 'bronze' => 2])
        ->and($table['weights'])->toBeNull();
});

it('shares a rank between identical medal counts', function () {
    $owner = cp_owner();
    $event = tk_event($owner);

    mt_finished($event, $owner, 'الف', [['x', 'مدرسه الف', 1]]);
    mt_finished($event, $owner, 'ب', [['y', 'مدرسه ب', 1]]);
    mt_finished($event, $owner, 'ج', [['z', 'مدرسه ج', 2]]);

    $ranks = collect(mt_table($event)['rows'])->pluck('rank', 'affiliation')->all();

    expect($ranks)->toBe(['مدرسه الف' => 1, 'مدرسه ب' => 1, 'مدرسه ج' => 3]);
});

it('ignores unfinished sports, withdrawn competitors and ranks below third', function () {
    $owner = cp_owner();
    $event = tk_event($owner);

    mt_finished($event, $owner, 'در حال برگزاری', [['a', 'مدرسه الف', 1]], 'running');
    mt_finished($event, $owner, 'پایان‌یافته', [
        ['b', 'مدرسه ب', 1],
        ['c', 'مدرسه ج', 2, 'withdrawn'],
        ['d', 'مدرسه د', 4],
        ['e', 'مدرسه ه', null],
    ]);

    $table = mt_table($event);

    expect($table['rows'])->toHaveCount(1)
        ->and($table['rows'][0]['affiliation'])->toBe('مدرسه ب')
        ->and($table['competitions'])->toBe(1);
});

it('groups affiliations ignoring case and spaces and reports unaffiliated medals', function () {
    $owner = cp_owner();
    $event = tk_event($owner);

    mt_finished($event, $owner, 'الف', [['a', 'Alborz', 1], ['b', null, 2], ['c', '  ', 3]]);
    mt_finished($event, $owner, 'ب', [['d', ' alborz ', 1]]);

    $table = mt_table($event);

    expect($table['rows'])->toHaveCount(1)
        ->and($table['rows'][0]['gold'])->toBe(2)
        ->and($table['unaffiliated'])->toBe(2);
});

it('does not mix medals of different events', function () {
    $owner = cp_owner();
    $one = tk_event($owner);
    $two = tk_event($owner);

    mt_finished($one, $owner, 'الف', [['a', 'مدرسه الف', 1]]);

    expect(mt_table($two)['rows'])->toBe([]);
});

it('lists each medal under its affiliation for transparency', function () {
    $owner = cp_owner();
    $event = tk_event($owner);
    mt_finished($event, $owner, 'فوتسال', [['تیم ۱', 'مدرسه الف', 1]]);

    expect(mt_table($event)['rows'][0]['entries'])->toBe([
        ['competition' => 'فوتسال', 'name' => 'تیم ۱', 'medal' => 'gold'],
    ]);
});

it('adds weighted points and can sort by them', function () {
    $owner = cp_owner();
    $event = tk_event($owner);
    $service = app(MedalTableService::class);

    // الف: ۱ طلا؛ ب: ۳ برنز. بر اساس مدال الف بالاتر است؛ با وزن‌های ۲/۱/۱ امتیاز ب (۳) از الف (۲) بیشتر می‌شود.
    mt_finished($event, $owner, 'الف', [['a', 'مدرسه الف', 1], ['b', 'مدرسه ب', 3]]);
    mt_finished($event, $owner, 'ب', [['c', 'مدرسه ب', 3]]);
    mt_finished($event, $owner, 'ج', [['d', 'مدرسه ب', 3]]);

    $service->configure($event, $owner, ['gold' => 2, 'silver' => 1, 'bronze' => 1], 'medals');
    $byMedals = collect(mt_table($event)['rows']);

    expect($byMedals->pluck('affiliation')->all())->toBe(['مدرسه الف', 'مدرسه ب'])
        ->and($byMedals->pluck('points', 'affiliation')->all())->toBe(['مدرسه الف' => 2, 'مدرسه ب' => 3]);

    $service->configure($event->refresh(), $owner, ['gold' => 2, 'silver' => 1, 'bronze' => 1], 'points');
    $byPoints = collect(mt_table($event)['rows']);

    expect($byPoints->pluck('affiliation')->all())->toBe(['مدرسه ب', 'مدرسه الف'])
        ->and(mt_table($event)['sort'])->toBe('points');
});

it('validates medal settings and can clear them', function () {
    $owner = cp_owner();
    $event = tk_event($owner);
    $service = app(MedalTableService::class);

    expect(fn () => $service->configure($event, $owner, ['gold' => -1, 'silver' => 1, 'bronze' => 1], 'medals'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $service->configure($event, $owner, ['gold' => 'x', 'silver' => 1, 'bronze' => 1], 'medals'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $service->configure($event, $owner, ['gold' => 1, 'silver' => 1], 'medals'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $service->configure($event, $owner, null, 'points'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $service->configure($event, $owner, null, 'weird'))->toThrow(InvalidArgumentException::class);

    $service->configure($event, $owner, ['gold' => 3, 'silver' => 2, 'bronze' => 1], 'points');
    expect($service->weights($event->refresh()))->toBe(['gold' => 3, 'silver' => 2, 'bronze' => 1]);

    $service->configure($event->refresh(), $owner, null, 'medals');
    expect($service->weights($event->refresh()))->toBeNull();
});

it('keeps other event metadata when saving medal settings', function () {
    $owner = cp_owner();
    $event = tk_event($owner);
    $event->update(['metadata' => ['budget' => ['overrun_threshold_percent' => 15]]]);

    app(MedalTableService::class)->configure($event->refresh(), $owner, ['gold' => 3, 'silver' => 2, 'bronze' => 1], 'medals');

    expect($event->refresh()->metadata['budget']['overrun_threshold_percent'])->toBe(15)
        ->and($event->metadata['medals']['weights']['gold'])->toBe(3);
});

it('shows the table to members and lets only managers change the settings', function () {
    $owner = cp_owner();
    $event = tk_event($owner);
    mt_finished($event, $owner, 'فوتسال', [['a', 'مدرسه الف', 1]]);

    $member = User::factory()->create();
    tk_join($event, $member);

    Livewire::actingAs($member)
        ->test(EventMedals::class, ['event' => $event])
        ->assertOk()
        ->assertSee('مدرسه الف')
        ->set('wEnabled', true)
        ->call('saveSettings')
        ->assertForbidden();

    Livewire::actingAs($owner)
        ->test(EventMedals::class, ['event' => $event])
        ->set('wEnabled', true)
        ->set('wGold', '5')
        ->set('wSilver', '3')
        ->set('wBronze', '1')
        ->call('saveSettings')
        ->assertSet('error', null);

    expect(app(MedalTableService::class)->weights($event->refresh()))->toBe(['gold' => 5, 'silver' => 3, 'bronze' => 1]);

    expect(cp_owner()->can('viewAny', [Competition::class, $event]))->toBeFalse();
});

it('shows an error instead of saving invalid weights', function () {
    $owner = cp_owner();
    $event = tk_event($owner);

    Livewire::actingAs($owner)
        ->test(EventMedals::class, ['event' => $event])
        ->set('wEnabled', true)
        ->set('wGold', '-4')
        ->call('saveSettings')
        ->assertSet('notice', null)
        ->assertNotSet('error', null);

    expect(app(MedalTableService::class)->weights($event->refresh()))->toBeNull();
});
