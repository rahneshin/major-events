<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;

it('has a Persian translation for every static key used in views and app code', function () {
    $missing = [];

    $files = collect(File::allFiles(resource_path('views')))
        ->merge(File::allFiles(app_path()))
        ->filter(fn ($f) => str_ends_with($f->getFilename(), '.php'));

    foreach ($files as $file) {
        preg_match_all("/(?:__|trans)\(\s*'([a-z_]+(?:\.[a-z_]+)+)'\s*[,)]/", $file->getContents(), $m);

        foreach ($m[1] as $key) {
            if (! Lang::has($key, 'fa', false)) {
                $missing[] = $key.' ← '.$file->getRelativePathname();
            }
        }
    }

    expect($missing)->toBe([]);
});

it('uses Persian as locale and fallback', function () {
    expect(config('app.locale'))->toBe('fa')
        ->and(config('app.fallback_locale'))->toBe('fa');
});
