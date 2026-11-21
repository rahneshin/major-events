<?php

namespace App\Providers;

use App\Models\Committee;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use App\Policies\CommitteePolicy;
use App\Policies\EventPolicy;
use App\Policies\OrganizationPolicy;
use App\Services\EntitlementService;
use App\Services\Sms\LogSmsGateway;
use App\Services\Sms\NullSmsGateway;
use App\Services\Sms\SmsGateway;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
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
        Gate::policy(Organization::class, OrganizationPolicy::class);
        Gate::policy(Event::class, EventPolicy::class);
        Gate::policy(Committee::class, CommitteePolicy::class);

        // لایه‌ی چهارم دسترسی: ادمین سیستم (/admin و /system)
        Gate::define('access-admin', fn (User $user): bool => $user->isSuperAdmin() && $user->isActive());

        Blade::if('feature', function (string $feature) {
            $user = Auth::user();

            if ($user === null) {
                return false;
            }

            return app(EntitlementService::class)->can($user, $feature);
        });
    }
}
