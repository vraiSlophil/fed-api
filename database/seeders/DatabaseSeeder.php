<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Création d'un utilisateur
        $user = User::factory()->create([
            'username' => 'owner',
            'email' => 'owner@example.com',
        ]);

        // Création de 2 thèmes dont il est propriétaire
        $themes = Theme::factory(2)->create([
            'owner_id' => $user->user_id,
        ]);

        // Pour chaque thème, création de 3 tâches
        foreach ($themes as $theme) {
            Task::factory(3)->create([
                'theme_id' => $theme->theme_id,
                'user_id' => $user->user_id,
            ]);
        }
    }
}
