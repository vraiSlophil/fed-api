<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Créer 50 utilisateurs avec la factory
        User::factory()->count(50)->create();

        // Optionnel : créer quelques utilisateurs avec des états spécifiques
        // Par exemple, 10 utilisateurs non vérifiés
        User::factory()->count(10)->unverified()->create();

        // Optionnel : créer quelques utilisateurs bloqués
        User::factory()->count(5)->create(['blocked_at' => now()]);
    }
}
