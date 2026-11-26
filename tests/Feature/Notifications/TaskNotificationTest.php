<?php

use App\Jobs\SendSmsMessage;
use App\Models\User;
use App\Services\NotificationPreferenceService;
use App\Services\Sms\SmsGateway;
use App\Services\TaskService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

function tn_scene(): array
{
    $owner = User::factory()->organizational()->create();
    $event = tk_event($owner);
    $member = User::factory()->create();
    tk_join($event, $member);

    return [$owner, $event, $member];
}

it('notifies the assignee in-app when a signed-in user assigns a task', function () {
    [$owner, $event, $member] = tn_scene();

    $this->actingAs($owner);
    tk_task($event, $owner, ['title' => 'آماده‌سازی سالن', 'assignee_id' => $member->id]);

    $n = $member->notifications()->first();

    expect($member->notifications()->count())->toBe(1)
        ->and($n->data['type'])->toBe('task.assigned')
        ->and($n->data['task_title'])->toBe('آماده‌سازی سالن')
        ->and($owner->notifications()->count())->toBe(0);
});

it('does not notify when there is no signed-in actor or the actor assigns themself', function () {
    [$owner, $event, $member] = tn_scene();

    tk_task($event, $owner, ['assignee_id' => $member->id]); // no actor (console/seed)
    expect($member->notifications()->count())->toBe(0);

    $this->actingAs($member);
    tk_task($event, $owner, ['assignee_id' => $member->id]); // self-assignment
    expect($member->notifications()->count())->toBe(0);
});

it('notifies on reassignment', function () {
    [$owner, $event, $member] = tn_scene();
    $other = User::factory()->create();
    tk_join($event, $other);

    $this->actingAs($owner);
    $task = tk_task($event, $owner, ['assignee_id' => $member->id]);

    app(TaskService::class)->update($task, ['assignee_id' => $other->id]);

    expect($other->notifications()->count())->toBe(1)
        ->and($member->notifications()->count())->toBe(1);
});

it('sends an SMS only for urgent assignments and only if the user allows it', function () {
    Queue::fake();
    [$owner, $event, $member] = tn_scene();
    $this->actingAs($owner);

    tk_task($event, $owner, ['assignee_id' => $member->id, 'priority' => 'medium']);
    Queue::assertNotPushed(SendSmsMessage::class);

    tk_task($event, $owner, ['assignee_id' => $member->id, 'priority' => 'urgent']);
    Queue::assertPushed(SendSmsMessage::class, fn (SendSmsMessage $job) => $job->phone === $member->phone);

    Queue::fake(); // reset
    app(NotificationPreferenceService::class)->set($member, 'task_assigned', true, false);
    tk_task($event, $owner, ['assignee_id' => $member->id, 'priority' => 'urgent']);
    Queue::assertNotPushed(SendSmsMessage::class);
});

it('stops sending SMS once the daily cap is reached but keeps the in-app notification', function () {
    Queue::fake();
    config(['notifications.sms.per_user_per_day' => 1]);
    [$owner, $event, $member] = tn_scene();
    $this->actingAs($owner);

    tk_task($event, $owner, ['assignee_id' => $member->id, 'priority' => 'urgent']);
    tk_task($event, $owner, ['assignee_id' => $member->id, 'priority' => 'urgent']);

    Queue::assertPushed(SendSmsMessage::class, 1);
    expect($member->notifications()->count())->toBe(2);
});

it('retries when the gateway reports a failure and logs the final error', function () {
    $this->app->bind(SmsGateway::class, fn () => new class implements SmsGateway
    {
        public function send(string $phone, string $message): bool
        {
            return false;
        }
    });

    $job = new SendSmsMessage('+989121234567', 'سلام', 1, 'task_assigned');

    expect($job->tries)->toBe(3)
        ->and($job->backoff())->toBe([30, 120])
        ->and(fn () => $job->handle(app(SmsGateway::class)))->toThrow(RuntimeException::class);

    Log::spy();
    $job->failed(new RuntimeException('boom'));
    Log::shouldHaveReceived('error')->once();
});
