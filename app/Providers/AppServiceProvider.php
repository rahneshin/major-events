<?php

namespace App\Providers;

use App\Services\EntitlementService;
use App\Services\Sms\LogSmsGateway;
use App\Services\Sms\NullSmsGateway;
use App\Services\Sms\SmsGateway;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EntitlementService::class);

        $this->app->bind(SmsGateway::class, function () {
            return match (config('sms.driver', 'log')) {
                'null' => new NullSmsGateway,
                default => new LogSmsGateway,
            };
        });
    }

    public function boot(): void
    {
        Blade::if('feature', function (string $feature) {
            $user = Auth::user();

            if ($user === null) {
                return false;
            }

            return app(EntitlementService::class)->can($user, $feature);
        });
    }
}
