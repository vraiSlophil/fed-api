<?php

namespace App\Observers;

use App\Models\Task;
use App\Models\Theme;
use App\Models\User;
use App\Models\UserMetric;

class UserMetricObserver
{
    public function created(User $user): void
    {
        // Créer automatiquement les métriques lors de la création d'un utilisateur
        UserMetric::create(['user_id' => $user->user_id]);
    }

    public function updated(User $user): void
    {
        // Mettre à jour la dernière activité si nécessaire
        if ($user->wasChanged(['last_login_at'])) {
            UserMetric::updateUserMetrics($user->user_id);
        }
    }
}
