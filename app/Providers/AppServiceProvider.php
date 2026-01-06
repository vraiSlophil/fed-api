<?php

namespace App\Providers;

use App\Models\Task;
use App\Models\Theme;
use App\Models\User;
use App\Observers\TaskObserver;
use App\Observers\ThemeObserver;
use App\Observers\UserObserver;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url')."/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });
        User::observe(UserObserver::class);
        Task::observe(TaskObserver::class);
        Theme::observe(ThemeObserver::class);
    }
}
