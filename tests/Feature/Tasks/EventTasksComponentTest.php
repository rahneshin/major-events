<?php

use App\Enums\CommitteeRole;
use App\Enums\EventRole;
use App\Enums\EventStatus;
use App\Enums\MilestoneStatus;
use App\Enums\TaskStatus;
use App\Livewire\Events\EventTasks;
use App\Models\Milestone;
use App\Models\Task;
use App\Models\User;
use App\Services\CommitteeService;
use App\Services\TaskService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/** @return array{0: User, 1: \App\Models\Event} */
function tkui_scene(): array
{
    $owner = User::factory()->organizational()->create();

    return [$owner, tk_event($owner)];
}

// ----------------------------------------------------------------- access

it('renders the tasks tab for every event member and shows the tab from EventShow', function () {
    [$owner, $event] = tkui_scene();
    $viewer = User::factory()->create();
    tk_join($event, $viewer, EventRole::Viewer);
    tk_task($event, $owner, ['title' => 'رزرو سالن اصلی']);

    foreach ([$owner, $viewer] as $user) {
        Livewire::actingAs($user)->test(EventTasks::class, ['event' => $event])
            ->assertOk()
            ->assertSee('رزرو سالن اصلی');
    }

    $this->actingAs($owner)->get(route('events.show', ['event' => $event, 'tab' => 'tasks']))->assertOk();
});

it('forbids the tab for users outside the event', function () {
    [, $event] = tkui_scene();

    Livewire::actingAs(User::factory()->organizational()->create())
        ->test(EventTasks::class, ['event' => $event])
        ->assertForbidden();
});

// ----------------------------------------------------------------- create

it('lets a manager create, edit and delete a task through the form', function () {
    [$owner, $event] = tkui_scene();
    $member = User::factory()->create();
    tk_join($event, $member);

    $component = Livewire::actingAs($owner)->test(EventTasks::class, ['event' => $event])
        ->call('openCreate')
        ->set('title', 'چاپ بنر')
        ->set('priority', 'high')
        ->set('assigneeId', (string) $member->id)
        ->set('dueAt', '۱۴۰۵/۱۲/۱۵')
        ->call('save')
        ->assertHasNoErrors();

    $task = Task::query()->where('title', 'چاپ بنر')->firstOrFail();

    expect($task->event_id)->toBe($event->id)
        ->and($task->assignee_id)->toBe($member->id)
        ->and($task->due_at)->not->toBeNull()
        // موعد = پایان همان روز
        ->and($task->due_at->format('H:i'))->toBe('23:59');

    $component->call('openEdit', $task->id)
        ->assertSet('title', 'چاپ بنر')
        ->set('title', 'چاپ بنر جدید')
        ->call('save');

    expect($task->fresh()->title)->toBe('چاپ بنر جدید');

    $component->call('deleteTask', $task->id);

    expect(Task::query()->whereKey($task->id)->exists())->toBeFalse();
});

it('shows validation and business errors instead of crashing', function () {
    [$owner, $event] = tkui_scene();

    Livewire::actingAs($owner)->test(EventTasks::class, ['event' => $event])
        ->call('openCreate')
        ->set('title', '')
        ->call('save')
        ->assertHasErrors(['title'])
        ->set('title', 'ok')
        ->set('startAt', '۱۴۰۵/۱۲/۱۵')
        ->set('dueAt', '۱۴۰۵/۱۲/۰۱')
        ->call('save')
        ->assertSet('error', __('task.due_before_start'))
        ->set('startAt', 'نامعتبر')
        ->call('save')
        ->assertHasErrors(['startAt']);
});

it('stops at the plan limit with a readable message', function () {
    config(['plans.individual.limits.tasks_per_event' => 1]);

    $owner = User::factory()->individual()->create();
    $event = tk_event($owner);
    tk_task($event, $owner);

    Livewire::actingAs($owner)->test(EventTasks::class, ['event' => $event])
        ->call('openCreate')
        ->set('title', 'وظیفه‌ی دوم')
        ->call('save')
        ->assertSet('error', fn ($error) => is_string($error) && $error !== '');

    expect(Task::query()->where('event_id', $event->id)->count())->toBe(1);
});

it('lets only manager-level users and committee heads open the create form', function () {
    [$owner, $event] = tkui_scene();
    $committees = app(CommitteeService::class);
    $own = $committees->create($event, $owner, ['name' => 'الف']);
    $other = $committees->create($event, $owner, ['name' => 'ب']);

    $head = User::factory()->create();
    tk_join($event, $head);
    $committees->addMember($own, $owner, $head, CommitteeRole::Head);

    $member = User::factory()->create();
    tk_join($event, $member);

    // plain member and viewer: 403
    Livewire::actingAs($member)->test(EventTasks::class, ['event' => $event])
        ->call('openCreate')->assertForbidden();

    // head: may create inside own committee only
    Livewire::actingAs($head)->test(EventTasks::class, ['event' => $event])
        ->call('openCreate')
        ->set('title', 'کار کمیته الف')
        ->set('committeeId', (string) $own->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(Task::query()->where('title', 'کار کمیته الف')->exists())->toBeTrue();

    Livewire::actingAs($head)->test(EventTasks::class, ['event' => $event])
        ->call('openCreate')
        ->set('title', 'کار کمیته ب')
        ->set('committeeId', (string) $other->id)
        ->call('save')
        ->assertForbidden();

    expect(Task::query()->where('title', 'کار کمیته ب')->exists())->toBeFalse();
});

it('forbids edit and delete for people without the right', function () {
    [$owner, $event] = tkui_scene();
    $member = User::factory()->create();
    tk_join($event, $member);
    $task = tk_task($event, $owner, ['assignee_id' => $member->id]);

    Livewire::actingAs($member)->test(EventTasks::class, ['event' => $event])
        ->call('openEdit', $task->id)->assertForbidden();

    Livewire::actingAs($member)->test(EventTasks::class, ['event' => $event])
        ->call('deleteTask', $task->id)->assertForbidden();

    expect(Task::query()->whereKey($task->id)->exists())->toBeTrue();
});

// ------------------------------------------------------- status and board

it('lets the assignee move their own task but not someone else’s', function () {
    [$owner, $event] = tkui_scene();
    $assignee = User::factory()->create();
    tk_join($event, $assignee);
    $mine = tk_task($event, $owner, ['assignee_id' => $assignee->id]);
    $theirs = tk_task($event, $owner);

    $c = Livewire::actingAs($assignee)->test(EventTasks::class, ['event' => $event])->set('mode', 'board');

    $c->call('changeStatus', $mine->id, 'doing');
    expect($mine->fresh()->status)->toBe(TaskStatus::Doing);

    $c->call('changeStatus', $theirs->id, 'doing')->assertForbidden();
    expect($theirs->fresh()->status)->toBe(TaskStatus::Todo);
});

it('never lets a viewer change a status', function () {
    [$owner, $event] = tkui_scene();
    $viewer = User::factory()->create();
    tk_join($event, $viewer, EventRole::Viewer);
    $task = tk_task($event, $owner);

    Livewire::actingAs($viewer)->test(EventTasks::class, ['event' => $event])
        ->call('changeStatus', $task->id, 'done')
        ->assertForbidden();

    expect($task->fresh()->status)->toBe(TaskStatus::Todo);
});

it('rejects dropping a card into the automatic blocked column or past its prerequisites', function () {
    [$owner, $event] = tkui_scene();
    $svc = app(TaskService::class);
    $prereq = tk_task($event, $owner);
    $task = tk_task($event, $owner);
    $svc->addDependency($task, $prereq);

    $c = Livewire::actingAs($owner)->test(EventTasks::class, ['event' => $event])->set('mode', 'board');

    $c->call('changeStatus', $prereq->id, 'blocked')
        ->assertSet('error', __('task.blocked_is_automatic'));

    $c->call('changeStatus', $task->id, 'doing')
        ->assertSet('error', __('task.blocked_by_prerequisites'));

    expect($task->fresh()->status)->toBe(TaskStatus::Blocked);

    $c->call('changeStatus', $prereq->id, 'done')->assertSet('error', null);
    expect($task->fresh()->status)->toBe(TaskStatus::Todo);
});

it('does not touch tasks of another event when the id is foreign', function () {
    [$owner, $event] = tkui_scene();
    $foreign = tk_task(tk_event($owner), $owner);

    foreach (['changeStatus' => [$foreign->id, 'done'], 'openTask' => [$foreign->id], 'deleteTask' => [$foreign->id]] as $method => $args) {
        tk_blocked(fn () => Livewire::actingAs($owner)->test(EventTasks::class, ['event' => $event])->call($method, ...$args));
    }

    expect($foreign->fresh()->status)->toBe(TaskStatus::Todo);
});

it('makes a closed event read-only', function () {
    [$owner, $event] = tkui_scene();
    $task = tk_task($event, $owner);
    $event->forceFill(['status' => EventStatus::Archived])->save();

    Livewire::actingAs($owner)->test(EventTasks::class, ['event' => $event->fresh()])
        ->call('changeStatus', $task->id, 'done')
        ->assertSet('error', __('task.ui.event_closed'));

    expect($task->fresh()->status)->toBe(TaskStatus::Todo);
});

// ------------------------------------------------------------------ filters

it('filters by status, mine, overdue and search', function () {
    [$owner, $event] = tkui_scene();
    $svc = app(TaskService::class);

    $late = tk_task($event, $owner, ['title' => 'کار دیرکرده', 'due_at' => now()->subDays(2)]);
    $done = tk_task($event, $owner, ['title' => 'کار تمام', 'assignee_id' => $owner->id]);
    $svc->transition($done, TaskStatus::Done);
    tk_task($event, $owner, ['title' => 'کار عادی']);

    Livewire::actingAs($owner)->test(EventTasks::class, ['event' => $event])
        ->set('fDue', 'overdue')->assertSee('کار دیرکرده')->assertDontSee('کار عادی')
        ->set('fDue', '')->set('fStatus', 'done')->assertSee('کار تمام')->assertDontSee('کار عادی')
        ->set('fStatus', '')->set('fMine', true)->assertSee('کار تمام')->assertDontSee('کار عادی')
        ->set('fMine', false)->set('fSearch', 'عادی')->assertSee('کار عادی')->assertDontSee('کار دیرکرده')
        ->call('resetFilters')->assertSee('کار دیرکرده')->assertSee('کار عادی');
});

// -------------------------------------------------------------------- detail

it('opens a task from the deep link used by "my tasks" and notifications', function () {
    [$owner, $event] = tkui_scene();
    $task = tk_task($event, $owner, ['title' => 'کار پیوندی']);

    $this->actingAs($owner)
        ->get(route('events.show', ['event' => $event, 'tab' => 'tasks', 'task' => $task->uuid]))
        ->assertOk()
        ->assertSee('کار پیوندی');
});

it('manages subtasks and dependencies from the detail panel', function () {
    [$owner, $event] = tkui_scene();
    $a = tk_task($event, $owner, ['title' => 'الف']);
    $b = tk_task($event, $owner, ['title' => 'ب']);

    $c = Livewire::actingAs($owner)->test(EventTasks::class, ['event' => $event])
        ->call('openTask', $a->id)
        ->set('subtaskTitle', 'زیر الف')
        ->call('addSubtask')
        ->assertSet('subtaskTitle', '');

    expect(Task::query()->where('parent_id', $a->id)->count())->toBe(1);

    $c->set('depPick', (string) $b->id)->call('addDependency');
    expect($a->fresh()->status)->toBe(TaskStatus::Blocked);

    // cycle is reported, not thrown
    $c->call('openTask', $b->id)->set('depPick', (string) $a->id)->call('addDependency')
        ->assertSet('error', __('task.dependency_cycle'));

    $c->call('openTask', $a->id)->call('removeDependency', $b->id);
    expect($a->fresh()->status)->toBe(TaskStatus::Todo);
});

it('lets members comment but not viewers, and deletes only own comments', function () {
    [$owner, $event] = tkui_scene();
    $member = User::factory()->create();
    $viewer = User::factory()->create();
    tk_join($event, $member);
    tk_join($event, $viewer, EventRole::Viewer);
    $task = tk_task($event, $owner);

    Livewire::actingAs($member)->test(EventTasks::class, ['event' => $event])
        ->call('openTask', $task->id)
        ->set('commentBody', 'سلام تیم')
        ->call('addComment')
        ->assertSee('سلام تیم');

    $comment = $task->comments()->firstOrFail();

    Livewire::actingAs($viewer)->test(EventTasks::class, ['event' => $event])
        ->call('openTask', $task->id)
        ->set('commentBody', 'نظر بیننده')
        ->call('addComment')
        ->assertForbidden();

    $other = User::factory()->create();
    tk_join($event, $other);

    Livewire::actingAs($other)->test(EventTasks::class, ['event' => $event])
        ->call('openTask', $task->id)
        ->call('deleteComment', $comment->id)
        ->assertForbidden();

    Livewire::actingAs($member)->test(EventTasks::class, ['event' => $event])
        ->call('openTask', $task->id)
        ->call('deleteComment', $comment->id);

    expect($task->comments()->count())->toBe(0);
});

it('uploads and removes attachments from the detail panel', function () {
    Storage::fake('local');

    [$owner, $event] = tkui_scene();
    $task = tk_task($event, $owner);

    $c = Livewire::actingAs($owner)->test(EventTasks::class, ['event' => $event])
        ->call('openTask', $task->id)
        ->set('attachment', UploadedFile::fake()->createWithContent('plan.pdf', "%PDF-1.4\n1 0 obj\n<< >>\nendobj\ntrailer\n<< >>\n%%EOF\n"))
        ->call('uploadAttachment')
        ->assertHasNoErrors()
        ->assertSee('plan.pdf');

    $attachment = $task->attachments()->firstOrFail();
    Storage::disk('local')->assertExists($attachment->path);

    $c->call('removeAttachment', $attachment->id);

    Storage::disk('local')->assertMissing($attachment->path);
    expect($task->attachments()->count())->toBe(0);
});

it('reports a rejected file type without storing anything', function () {
    Storage::fake('local');

    [$owner, $event] = tkui_scene();
    $task = tk_task($event, $owner);

    Livewire::actingAs($owner)->test(EventTasks::class, ['event' => $event])
        ->call('openTask', $task->id)
        ->set('attachment', UploadedFile::fake()->create('virus.exe', 5, 'application/x-msdownload'))
        ->call('uploadAttachment')
        ->assertSet('error', __('task.attachment_type_not_allowed'));

    expect($task->attachments()->count())->toBe(0);
});

// ------------------------------------------------------------------ calendar

it('renders the Jalali calendar with due tasks and milestones', function () {
    [$owner, $event] = tkui_scene();
    tk_task($event, $owner, ['title' => 'کار تقویمی', 'due_at' => now()]);
    app(\App\Services\MilestoneService::class)->create($event, ['title' => 'افتتاحیه تقویم', 'due_at' => now()]);

    Livewire::actingAs($owner)->test(EventTasks::class, ['event' => $event])
        ->call('setView', 'calendar')
        ->assertSee('کار تقویمی')
        ->assertSee('افتتاحیه تقویم')
        ->call('nextMonth')
        ->assertDontSee('کار تقویمی')
        ->call('thisMonth')
        ->assertSee('کار تقویمی');
});

it('lists undated tasks under the calendar', function () {
    [$owner, $event] = tkui_scene();
    tk_task($event, $owner, ['title' => 'بدون موعد ویژه']);

    Livewire::actingAs($owner)->test(EventTasks::class, ['event' => $event])
        ->call('setView', 'calendar')
        ->assertSee('بدون موعد ویژه');
});

// ---------------------------------------------------------------- milestones

it('lets only managers manage milestones', function () {
    [$owner, $event] = tkui_scene();
    $member = User::factory()->create();
    tk_join($event, $member);

    $c = Livewire::actingAs($owner)->test(EventTasks::class, ['event' => $event])
        ->call('openMilestoneForm')
        ->set('msTitle', 'پایان ثبت‌نام')
        ->set('msDue', '۱۴۰۵/۱۲/۲۰')
        ->call('saveMilestone')
        ->assertHasNoErrors();

    $milestone = Milestone::query()->where('event_id', $event->id)->firstOrFail();

    $c->call('setMilestoneStatus', $milestone->id, 'reached');
    expect($milestone->fresh()->status)->toBe(MilestoneStatus::Reached);

    Livewire::actingAs($member)->test(EventTasks::class, ['event' => $event])
        ->call('openMilestoneForm')->assertForbidden();

    Livewire::actingAs($member)->test(EventTasks::class, ['event' => $event])
        ->call('deleteMilestone', $milestone->id)->assertForbidden();

    $c->call('deleteMilestone', $milestone->id);
    expect(Milestone::query()->count())->toBe(0);
});
