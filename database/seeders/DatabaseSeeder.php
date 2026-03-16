<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application with demo/reference data for non-test usage.
     *
     * Tests must seed only the exact deterministic classes they need.
     */
    public function run(): void
    {
        $this->call([
            RolesSeeder::class,
            CompleteDataSeeder::class,
        ]);
    }
}
