<?php

namespace App\Services\Sms;

interface SmsGateway
{
    /**
     * ارسال پیامک.
     *
     * @param  string  $phone  فرمت نرمال‌شده (+989xxxxxxxxx)
     * @param  string  $message  متن پیام
     */
    public function send(string $phone, string $message): bool;
}
