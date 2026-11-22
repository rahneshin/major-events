<?php

use App\Enums\EventRole;
use App\Exceptions\LimitExceededException;
use App\Models\TaskAttachment;
use App\Models\User;
use App\Services\TaskService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

function tk_pdf(string $name = 'report.pdf', int $kb = 100): UploadedFile
{
    return UploadedFile::fake()->create($name, $kb, 'application/pdf');
}

// ------------------------------------------------------------ validation

it('stores an allowed file on the private disk under a generated name', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $task = tk_task($event, $owner);

    $attachment = app(TaskService::class)->addAttachment($task, $owner, tk_pdf('گزارش نهایی.pdf', 200));

    expect($attachment->original_name)->toBe('گزارش نهایی.pdf')
        ->and($attachment->mime_type)->toBe('application/pdf')
        ->and($attachment->size)->toBe(200 * 1024)
        ->and($attachment->uuid)->not->toBeEmpty()
        ->and($attachment->disk)->toBe('local')
        ->and($attachment->path)->toStartWith("task-attachments/{$event->id}/{$task->id}/")
        ->and($attachment->path)->toEndWith('.pdf')
        ->and($attachment->path)->not->toContain('گزارش'); // never the client's name on disk

    Storage::disk('local')->assertExists($attachment->path);
});

it('strips path tricks from the stored original name', function () {
    $owner = User::factory()->organizational()->create();
    $task = tk_task(tk_event($owner), $owner);

    $attachment = app(TaskService::class)->addAttachment(
        $task,
        $owner,
        UploadedFile::fake()->create('../../etc/passwd.pdf', 10, 'application/pdf'),
    );

    expect($attachment->original_name)->toBe('passwd.pdf')
        ->and($attachment->path)->not->toContain('..');
});

it('rejects files that are too big, empty, or of a forbidden type', function () {
    $owner = User::factory()->organizational()->create();
    $task = tk_task(tk_event($owner), $owner);
    $svc = app(TaskService::class);

    // 6 MB > 5 MB per-file cap
    expect(fn () => $svc->addAttachment($task, $owner, tk_pdf('big.pdf', 6000)))->toThrow(InvalidArgumentException::class)
        // empty file
        ->and(fn () => $svc->addAttachment($task, $owner, tk_pdf('empty.pdf', 0)))->toThrow(InvalidArgumentException::class)
        // executable / script extensions
        ->and(fn () => $svc->addAttachment($task, $owner, UploadedFile::fake()->create('shell.php', 1, 'application/x-php')))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $svc->addAttachment($task, $owner, UploadedFile::fake()->create('setup.exe', 1, 'application/x-msdownload')))->toThrow(InvalidArgumentException::class)
        // active content that could run in the browser
        ->and(fn () => $svc->addAttachment($task, $owner, UploadedFile::fake()->create('x.html', 1, 'text/html')))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $svc->addAttachment($task, $owner, UploadedFile::fake()->create('x.svg', 1, 'image/svg+xml')))->toThrow(InvalidArgumentException::class)
        // extension says pdf but the real content type does not match
        ->and(fn () => $svc->addAttachment($task, $owner, UploadedFile::fake()->create('fake.pdf', 1, 'text/html')))->toThrow(InvalidArgumentException::class);

    expect(TaskAttachment::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

// ----------------------------------------------------------- storage limit

it('enforces the per-event attachment quota for individual accounts', function () {
    config(['plans.individual.limits.attachment_mb_per_event' => 1]);

    $owner = User::factory()->individual()->create();
    $event = tk_event($owner);
    $task = tk_task($event, $owner);
    $svc = app(TaskService::class);

    $first = $svc->addAttachment($task, $owner, tk_pdf('a.pdf', 600));

    expect($svc->storageUsedBytes($event))->toBe(600 * 1024)
        ->and(fn () => $svc->addAttachment($task, $owner, tk_pdf('b.pdf', 600)))->toThrow(LimitExceededException::class);

    // removing a file frees space
    $svc->removeAttachment($first);

    expect($svc->storageUsedBytes($event))->toBe(0)
        ->and($svc->addAttachment($task, $owner, tk_pdf('b.pdf', 600)))->toBeInstanceOf(TaskAttachment::class);
});

it('counts the quota per event, across all tasks of that event only', function () {
    config(['plans.individual.limits.attachment_mb_per_event' => 1]);

    $owner = User::factory()->individual()->create();
    $a = tk_event($owner);
    $svc = app(TaskService::class);

    $taskA1 = tk_task($a, $owner);
    $taskA2 = tk_task($a, $owner);
    $svc->addAttachment($taskA1, $owner, tk_pdf('a1.pdf', 600));

    // same event, different task → shares the quota
    expect(fn () => $svc->addAttachment($taskA2, $owner, tk_pdf('a2.pdf', 600)))->toThrow(LimitExceededException::class);

    // a different event has its own quota (individual accounts have 1 active event,
    // so use an organizational owner's second event with the same limit applied to them)
    config(['plans.organizational.limits.attachment_mb_per_event' => 1]);
    $org = User::factory()->organizational()->create();
    $b = tk_event($org);
    $taskB = tk_task($b, $org);

    expect($svc->addAttachment($taskB, $org, tk_pdf('b.pdf', 600)))->toBeInstanceOf(TaskAttachment::class);
});

it('does not limit attachment storage for organizational accounts', function () {
    $owner = User::factory()->organizational()->create();
    $task = tk_task(tk_event($owner), $owner);
    $svc = app(TaskService::class);

    foreach (range(1, 5) as $i) {
        $svc->addAttachment($task, $owner, tk_pdf("f{$i}.pdf", 4000));
    }

    expect(TaskAttachment::query()->count())->toBe(5);
});

// ---------------------------------------------------------------- removal

it('deletes the file and the row when an attachment is removed', function () {
    $owner = User::factory()->organizational()->create();
    $task = tk_task(tk_event($owner), $owner);
    $svc = app(TaskService::class);

    $attachment = $svc->addAttachment($task, $owner, tk_pdf());
    $path = $attachment->path;

    $svc->removeAttachment($attachment);

    Storage::disk('local')->assertMissing($path);
    expect(TaskAttachment::query()->count())->toBe(0);
});

it('lets only the uploader or a manager remove an attachment, through the right task', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $uploader = User::factory()->create();
    $other = User::factory()->create();
    tk_join($event, $uploader);
    tk_join($event, $other);

    $taskA = tk_task($event, $owner, ['assignee_id' => $uploader->id]);
    $taskB = tk_task($event, $owner);
    $attachment = app(TaskService::class)->addAttachment($taskA, $uploader, tk_pdf());

    expect($uploader->can('removeAttachment', [$taskA, $attachment]))->toBeTrue()
        ->and($owner->can('removeAttachment', [$taskA, $attachment]))->toBeTrue()
        ->and($other->can('removeAttachment', [$taskA, $attachment]))->toBeFalse()
        // attachment belongs to taskA, so nobody may remove it "through" taskB
        ->and($owner->can('removeAttachment', [$taskB, $attachment]))->toBeFalse();
});

// --------------------------------------------------------------- download

it('serves downloads to event members as an attachment with nosniff', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $viewer = User::factory()->create();
    tk_join($event, $viewer, EventRole::Viewer);

    $task = tk_task($event, $owner);
    $attachment = app(TaskService::class)->addAttachment($task, $owner, tk_pdf('report.pdf'));
    $url = route('tasks.attachments.download', [$event, $task, $attachment]);

    $this->actingAs($owner)->get($url)
        ->assertOk()
        ->assertDownload('report.pdf')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    // viewers can see the event, so they can download
    $this->actingAs($viewer)->get($url)->assertOk();
});

it('forbids downloads for users outside the event', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $task = tk_task($event, $owner);
    $attachment = app(TaskService::class)->addAttachment($task, $owner, tk_pdf());
    $url = route('tasks.attachments.download', [$event, $task, $attachment]);

    $this->actingAs(User::factory()->individual()->create())->get($url)->assertForbidden();
    $this->actingAs(User::factory()->organizational()->create())->get($url)->assertForbidden();
});

it('redirects guests to the login page instead of serving the file', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $task = tk_task($event, $owner);
    $attachment = app(TaskService::class)->addAttachment($task, $owner, tk_pdf());

    $this->get(route('tasks.attachments.download', [$event, $task, $attachment]))
        ->assertRedirect(route('auth.login'));
});

it('sends users without an account type to onboarding instead of the file', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $task = tk_task($event, $owner);
    $attachment = app(TaskService::class)->addAttachment($task, $owner, tk_pdf());

    $this->actingAs(User::factory()->withoutAccountType()->create())
        ->get(route('tasks.attachments.download', [$event, $task, $attachment]))
        ->assertRedirect(route('auth.select-account-type'));
});

it('returns 404 when the attachment is requested through the wrong task or event (no leaking)', function () {
    $owner = User::factory()->organizational()->create();
    $eventA = tk_event($owner);
    $eventB = tk_event($owner);
    $taskA = tk_task($eventA, $owner);
    $otherTaskInA = tk_task($eventA, $owner);
    $taskB = tk_task($eventB, $owner);
    $attachment = app(TaskService::class)->addAttachment($taskA, $owner, tk_pdf());

    // right attachment, wrong task (same event)
    $this->actingAs($owner)
        ->get(route('tasks.attachments.download', [$eventA, $otherTaskInA, $attachment]))
        ->assertNotFound();

    // right attachment, task from another event
    $this->actingAs($owner)
        ->get(route('tasks.attachments.download', [$eventB, $taskB, $attachment]))
        ->assertNotFound();

    // right task, wrong event
    $this->actingAs($owner)
        ->get(route('tasks.attachments.download', [$eventB, $taskA, $attachment]))
        ->assertNotFound();
});

it('returns 404 when the file is missing on disk', function () {
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $task = tk_task($event, $owner);
    $attachment = app(TaskService::class)->addAttachment($task, $owner, tk_pdf());

    Storage::disk('local')->delete($attachment->path);

    $this->actingAs($owner)
        ->get(route('tasks.attachments.download', [$event, $task, $attachment]))
        ->assertNotFound();
});
