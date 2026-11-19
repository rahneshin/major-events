<?php

namespace App\Providers;

use App\Services\Sms\LogSmsGateway;
use App\Services\Sms\NullSmsGateway;
use App\Services\Sms\SmsGateway;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SmsGateway::class, function () {
            return match (config('sms.driver', 'log')) {
                'null' => new NullSmsGateway,
                default => new LogSmsGateway,
            };
        });
    }

    public function boot(): void
    {
        //
    }
}
