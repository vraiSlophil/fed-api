<?php

namespace App\Observers;

use App\Models\Auth\User;
use App\Models\Metrics\UserMetric;
use App\Models\Playgrounds\Playground;

class UserObserver
{
    /**
     * Handle side effects triggered after model creation.
     *
     * @param  User  $user  Newly created user model that triggered the observer.
     * @return void No return value.
     */
    public function created(User $user): void
    {
        UserMetric::create(['user_id' => $user->user_id]);

        $defaultPlayground = Playground::create([
            'user_id' => $user->user_id,
            'name' => 'Main Workspace',
            'slug' => 'main',
            'icon' => 'home', // Icon name from the Google icon set.
            'color' => $this->generateRandomColor(),
            'is_default' => true,
        ]);

        $user->update(['active_playground_id' => $defaultPlayground->playground_id]);
    }

    /**
     * Generate a random hex color value for the default playground.
     *
     * @return string Random `#RRGGBB` color value assigned to generated playgrounds.
     */
    private function generateRandomColor(): string
    {
        return sprintf('#%06X', mt_rand(0, 0xFFFFFF));
    }
}
