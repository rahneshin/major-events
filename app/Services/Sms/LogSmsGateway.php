<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

class LogSmsGateway implements SmsGateway
{
    public function send(string $phone, string $message): bool
    {
        Log::channel('single')->info('[SMS]', [
            'phone' => $phone,
            'message' => $message,
        ]);

        return true;
    }
}
