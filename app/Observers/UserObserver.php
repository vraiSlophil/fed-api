<?php

namespace App\Observers;

use App\Models\Auth\User;
use App\Models\Metrics\UserMetric;
use App\Models\Playgrounds\Playground;

class UserObserver
{
    /**
     * Gérer l'événement "created" du modèle User
     */
    public function created(User $user): void
    {
        UserMetric::create(['user_id' => $user->user_id]);

        $defaultPlayground = Playground::create([
            'user_id' => $user->user_id,
            'name' => 'Mon Espace Principal',
            'slug' => 'principal',
            'icon' => 'home', // Nom de l'icone sur la banque d'icone de google
            'color' => $this->generateRandomColor(),
            'is_default' => true,
        ]);

        $user->update(['active_playground_id' => $defaultPlayground->playground_id]);
    }

    private function generateRandomColor(): string
    {
        return sprintf('#%06X', mt_rand(0, 0xFFFFFF));
    }
}
