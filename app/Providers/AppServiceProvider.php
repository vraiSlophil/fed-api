<?php

namespace App\Providers;

use App\Http\Responses\ApiResponse;
use App\Models\Auth\User;
use App\Models\Tasks\Task;
use App\Models\Themes\Theme;
use App\Observers\TaskObserver;
use App\Observers\ThemeObserver;
use App\Observers\UserObserver;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register application services, bindings, and global defaults.
     *
     * @return void No return value.
     */
    public function register(): void {}

    /**
     * Boot application services after all providers are registered.
     *
     * @return void No return value.
     */
    public function boot(): void
    {
        Factory::guessFactoryNamesUsing(
            static fn (string $modelName) => 'Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        RateLimiter::for('auth-login', function (Request $request) {
            $email = (string) $request->input('email', '');
            $key = Str::transliterate(Str::lower($email)).'|'.$request->ip();

            return Limit::perMinute(5)->by($key)->response(function ($request, $headers) {
                $seconds = (int) ($headers['Retry-After'] ?? 60);
                $minutes = (int) ceil($seconds / 60);

                return ApiResponse::builder()
                    ->error(429, 'Too many attempts')
                    ->messageCode('auth.throttle', ['seconds' => $seconds, 'minutes' => $minutes])
                    ->headers($headers)
                    ->json();
            });
        });

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

        VerifyEmail::createUrlUsing(function (object $notifiable) {
            $relativeUrl = URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(Config::get('auth.verification.expire', 60)),
                [
                    'id' => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ],
                false
            );

            $frontendBase = rtrim(config('app.frontend_url'), '/');
            $frontendPath = '/'.ltrim(config('app.frontend_verify_email_path', '/verify-email'), '/');
            $query = parse_url($relativeUrl, PHP_URL_QUERY);

            return $query ? $frontendBase.$frontendPath.'?'.$query : $frontendBase.$frontendPath;
        });
        User::observe(UserObserver::class);
        Task::observe(TaskObserver::class);
        Theme::observe(ThemeObserver::class);
    }
}
