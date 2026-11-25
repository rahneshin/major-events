<?php

use App\Models\Competition;
use App\Models\Event;
use App\Models\User;
use App\Services\CompetitionService;
use App\Services\DrawService;
use App\Services\RegistrationService;

/**
 * Helper های تست Step 12 (پیشوند `cp_` تا با بقیه تداخل نکند).
 */
function cp_owner(): User
{
    return User::factory()->organizational()->create();
}

function cp_competition(Event $event, User $actor, string $template = 'school_league', string $name = 'فوتسال'): Competition
{
    return app(CompetitionService::class)->create($event, $actor, [
        'template_code' => $template,
        'name' => $name,
    ]);
}

/** n شرکت‌کننده‌ی ثبت‌شده و تأییدشده. */
function cp_fill(Competition $competition, User $actor, int $count): void
{
    $registration = app(RegistrationService::class);

    foreach (range(1, $count) as $i) {
        $competitor = $registration->register($competition, $actor, ['name' => 'تیم '.$i, 'affiliation' => 'مدرسه '.(($i % 4) + 1)]);
        $registration->confirm($competitor, $actor);
    }
}

/** قرعه‌ی نهایی + ساخت جدول؛ تعداد بازی‌ها را برمی‌گرداند. */
function cp_draw(Competition $competition, User $actor, string $seed = 'test-seed'): int
{
    $draw = app(DrawService::class);
    $draw->confirm($competition->refresh(), $actor, 'random', $seed);

    return $draw->generateFixtures($competition->refresh(), $actor);
}
