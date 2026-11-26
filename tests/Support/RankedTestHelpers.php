<?php

require_once __DIR__.'/CompetitionTestHelpers.php';

use App\Models\Competition;
use App\Models\Heat;
use App\Models\User;
use App\Services\CompetitionService;
use App\Services\HeatService;
use App\Services\RegistrationService;
use Illuminate\Support\Collection;

/**
 * Helper های تست Step 12E (پیشوند `rk_`).
 */

/** @return array{0: User, 1: \App\Models\Event, 2: Competition} */
function rk_setup(string $template = 'ranked_heats', int $count = 10, string $name = '۱۰۰ متر'): array
{
    $owner = cp_owner();
    $event = tk_event($owner);
    $competition = cp_competition($event, $owner, $template, $name);

    if ($count > 0) {
        rk_fill($competition, $owner, $count);
    }

    return [$owner, $event, $competition];
}

function rk_fill(Competition $competition, User $actor, int $count, string $prefix = 'ورزشکار '): void
{
    $registration = app(RegistrationService::class);
    $affiliations = ['مدرسه الف', 'مدرسه ب', 'مدرسه ج'];

    foreach (range(1, $count) as $i) {
        $competitor = $registration->register($competition, $actor, [
            'name' => $prefix.$i,
            'affiliation' => $affiliations[$i % 3],
        ]);
        $registration->confirm($competitor, $actor);
    }
}

/** قرعه + آماده‌شدن. */
function rk_draw(Competition $competition, User $actor, string $seed = 'rk-seed'): void
{
    $heats = app(HeatService::class);
    $heats->draw($competition->refresh(), $actor, 'random', $seed);
    $heats->markReady($competition->refresh(), $actor);
}

/** @return Collection<int, Heat> سری‌های مرحله‌ی $position با نتیجه‌ها. */
function rk_heats(Competition $competition, int $position = 1): Collection
{
    $stage = $competition->stages()->where('position', $position)->firstOrFail();

    return Heat::query()->where('stage_id', $stage->id)->with('results.competitor')->orderBy('position')->get();
}

/**
 * ثبت نتیجه‌ی یک سری؛ مقدارها به ترتیب مسیر (lane). رشته‌ی DNS/DNF/DQ = وضعیت.
 *
 * @param  list<float|int|string>  $values
 */
function rk_enter(Heat $heat, User $actor, array $values, bool $canConfirm = true): Heat
{
    $heat = Heat::query()->with('results')->findOrFail($heat->id);
    $rows = [];

    foreach ($heat->results as $i => $result) {
        $v = $values[$i] ?? null;

        $rows[$result->competitor_id] = in_array($v, ['DNS', 'DNF', 'DQ'], true)
            ? ['status' => $v, 'value' => null]
            : ['status' => 'ok', 'value' => (string) $v];
    }

    return app(HeatService::class)->enterResults($heat, $actor, $rows, $canConfirm);
}

/** همه‌ی سری‌های یک مرحله با زمان‌های متمایز (۱۰٫۱، ۱۰٫۲، …). برمی‌گرداند competitor_id ← زمان. */
function rk_enter_stage(Competition $competition, User $actor, int $position = 1, float $start = 10.0, float $step = 0.1): array
{
    $times = [];
    $k = 0;

    foreach (rk_heats($competition, $position) as $heat) {
        $values = [];
        foreach ($heat->results as $result) {
            $k++;
            $values[] = $time = round($start + $k * $step, 3);
            $times[$result->competitor_id] = $time;
        }
        rk_enter($heat, $actor, $values);
    }

    return $times;
}
