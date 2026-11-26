<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Sms\SmsGateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Step 13 — queued SMS delivery with retry/backoff and an error log.
 * A gateway answering `false` counts as a failure and is retried.
 */
class SendSmsMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public function __construct(
        public string $phone,
        public string $message,
        public ?int $userId = null,
        public string $category = '',
    ) {}

    /** @return list<int> seconds between attempts */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(SmsGateway $gateway): void
    {
        if (! $gateway->send($this->phone, $this->message)) {
            throw new RuntimeException('SMS gateway reported a failure.');
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('SMS delivery failed', [
            'user_id' => $this->userId,
            'category' => $this->category,
            'phone' => substr($this->phone, 0, 6).'****'.substr($this->phone, -2),
            'error' => $e->getMessage(),
        ]);
    }
}
