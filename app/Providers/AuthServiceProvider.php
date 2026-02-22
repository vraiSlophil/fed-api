<?php

namespace App\Providers;

use App\Models\Auth\User;
use App\Models\Invitations\Invitation;
use App\Models\Playgrounds\Playground;
use App\Models\Tasks\Task;
use App\Models\Themes\Theme;
use App\Policies\AdminUserPolicy;
use App\Policies\InvitationPolicy;
use App\Policies\PlaygroundPolicy;
use App\Policies\TaskPolicy;
use App\Policies\ThemePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Theme::class => ThemePolicy::class,
        Task::class => TaskPolicy::class,
        Playground::class => PlaygroundPolicy::class,
        Invitation::class => InvitationPolicy::class,
        User::class => AdminUserPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
