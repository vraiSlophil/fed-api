<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Vérifier si les rôles existent déjà
        if (DB::table('roles')->count() > 0) {
            $this->command->info('Les rôles existent déjà, ignoré.');

            return;
        }

        DB::table('roles')->insert([
            [
                'power' => 10,
                'name' => 'user',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'power' => 100,
                'name' => 'admin',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'power' => 1000,
                'name' => 'superadmin',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->command->info('Rôles créés avec succès.');
    }
}
