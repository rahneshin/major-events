<?php

namespace App\Providers;

use App\Models\Task;
use App\Observers\TaskObserver;
use Illuminate\Support\ServiceProvider;

class NotificationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Task::observe(TaskObserver::class);
    }
}
