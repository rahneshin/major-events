<?php

namespace App\Services\Sms;

class NullSmsGateway implements SmsGateway
{
    public function send(string $phone, string $message): bool
    {
        return true;
    }
}
