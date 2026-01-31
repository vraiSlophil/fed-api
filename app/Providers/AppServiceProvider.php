<?php

namespace App\Providers;

use App\Models\Task;
use App\Models\Theme;
use App\Models\User;
use App\Observers\TaskObserver;
use App\Observers\ThemeObserver;
use App\Observers\UserObserver;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        RateLimiter::for('auth-refresh', function (Request $request) {
            $token = $request->header('X-Refresh-Token') ?: $request->bearerToken();
            $key = $request->ip();

            if (is_string($token) && $token !== '') {
                $key .= '|'.sha1($token);
            }

            return Limit::perMinute(10)->by($key);
        });

        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url')."/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });
        User::observe(UserObserver::class);
        Task::observe(TaskObserver::class);
        Theme::observe(ThemeObserver::class);
    }
}
