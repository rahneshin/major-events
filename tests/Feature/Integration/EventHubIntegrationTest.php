<?php

/*
 | Cross-step integration checks for steps 11 (sponsorship/marketing),
 | 12 (competitions) and 13 (dashboard/notifications).
 |
 | These steps were built on separate branches and all touch the event hub
 | (EventShow + event-show.blade + lang/fa/*.php + routes/web.php).
 | The checks below catch the typical merge damage: a tab that exists in the
 | view but not in the ?tab= whitelist (or vice-versa), a missing translation,
 | a lost route, or leftover conflict markers / junk files.
 */

use App\Livewire\Events\EventCompetitions;
use App\Livewire\Events\EventControlCenter;
use App\Livewire\Events\EventDashboard;
use App\Livewire\Events\EventMarketing;
use App\Livewire\Events\EventShow;
use App\Livewire\Events\EventSponsorship;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

/** @return array<string, string> tab key => translation key, exactly as drawn in the hub view */
function hub_view_tab_labels(): array
{
    $blade = file_get_contents(resource_path('views/livewire/events/event-show.blade.php'));
    preg_match_all("/^\s*'([a-z]+)' => __\('((?:event\.tab_[a-z_]+)|(?:[a-z]+\.tab))'\),/m", $blade, $m);

    return array_combine($m[1], $m[2]);
}

/** @return list<string> tab keys drawn as buttons in the hub view */
function hub_view_tabs(): array
{
    return array_keys(hub_view_tab_labels());
}

/** @return list<string> tab keys accepted from ?tab= by EventShow::mount() */
function hub_whitelist_tabs(): array
{
    $php = file_get_contents(app_path('Livewire/Events/EventShow.php'));
    preg_match('/in_array\(\$requested, \[(.*?)\], true\)/s', $php, $m);
    preg_match_all("/'([a-z]+)'/", $m[1] ?? '', $keys);

    return $keys[1];
}

it('keeps the ?tab= whitelist in EventShow in sync with the tab buttons', function () {
    $view = hub_view_tabs();

    expect($view)->not->toBeEmpty()
        ->and($view)->toContain('dashboard', 'control', 'sponsorship', 'marketing', 'competitions')
        ->and(hub_whitelist_tabs())->toEqualCanonicalizing($view);
});

it('has a render branch for every tab button', function () {
    $blade = file_get_contents(resource_path('views/livewire/events/event-show.blade.php'));

    foreach (hub_view_tabs() as $tab) {
        expect($blade)->toContain("\$tab === '{$tab}'");
    }
});

it('has a persian label for every tab', function () {
    foreach (hub_view_tab_labels() as $tab => $key) {
        expect(__($key))->not->toBe($key, "tab [{$tab}] shows its raw translation key");
    }
});

it('opens every event hub tab by url', function (string $tab) {
    $this->withoutVite();
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);

    $this->actingAs($owner)
        ->get(route('events.show', ['event' => $event, 'tab' => $tab]))
        ->assertOk()
        ->assertDontSee('event.tab_')
        ->assertDontSee('competition.tab');
})->with(fn () => hub_view_tabs());

it('mounts the sub-components of steps 11, 12 and 13 from the hub', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);

    foreach ([
        'dashboard' => EventDashboard::class,
        'control' => EventControlCenter::class,
        'sponsorship' => EventSponsorship::class,
        'marketing' => EventMarketing::class,
        'competitions' => EventCompetitions::class,
    ] as $tab => $component) {
        Livewire::actingAs($owner)->test(EventShow::class, ['event' => $event])
            ->set('tab', $tab)
            ->assertSeeLivewire($component);
    }
});

it('keeps the routes of steps 12 and 13 registered next to the older ones', function () {
    foreach (['events.show', 'tasks.mine', 'competitions.show', 'notifications.index'] as $name) {
        expect(Route::has($name))->toBeTrue("route [{$name}] is missing");
    }
});

it('has no merge-conflict markers in the source tree', function () {
    $hits = [];

    foreach (['app', 'bootstrap', 'config', 'database', 'lang', 'resources', 'routes', 'tests'] as $dir) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(base_path($dir), FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getRealPath() === __FILE__
                || ! preg_match('/\.(php|md|js|css)$/', $file->getFilename())) {
                continue;
            }

            if (preg_match('/^(<{7} |>{7} )/m', file_get_contents($file->getRealPath()))) {
                $hits[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getRealPath());
            }
        }
    }

    expect($hits)->toBe([]);
});

it('has no stray files with odd names in the project root', function () {
    $odd = [];

    foreach (glob(base_path('*')) ?: [] as $path) {
        if (! preg_match('/^[A-Za-z0-9._-]+$/', basename($path))) {
            $odd[] = basename($path);
        }
    }

    expect($odd)->toBe([]);
});
