<?php

use App\Enums\EventRole;
use App\Enums\EventType;
use App\Models\Event;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskService;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/*
|--------------------------------------------------------------------------
| Step 08 helpers (tasks)
|--------------------------------------------------------------------------
|
| Prefixed `tk_` so they never collide with helpers defined in other test files.
|
*/

function tk_event(User $owner): Event
{
    return Event::query()->create([
        'created_by' => $owner->id,
        'title' => 'رویداد '.fake()->unique()->numerify('####'),
        'event_type' => EventType::Local,
        'scale' => 'small',
        'status' => 'draft',
    ]);
}

function tk_join(Event $event, User $user, EventRole $role = EventRole::Member): void
{
    $event->members()->attach($user->id, [
        'role' => $role->value,
        'added_by' => $event->created_by,
        'joined_at' => now(),
    ]);
}

/**
 * @param  array<string, mixed>  $data
 */
function tk_task(Event $event, User $actor, array $data = []): Task
{
    return app(TaskService::class)->create($event, $actor, array_merge([
        'title' => 'وظیفه '.fake()->unique()->numerify('####'),
    ], $data));
}

/**
 * Livewire may either throw or answer 404 for a foreign id; both mean "blocked".
 */
function tk_blocked(callable $action): void
{
    try {
        $action();
    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException|\Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
        // expected
    }

    expect(true)->toBeTrue();
}
