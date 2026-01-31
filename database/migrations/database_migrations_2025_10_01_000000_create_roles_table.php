<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->smallInteger('power')->primary();
            $table->string('name', 50)->unique();
            $table->timestamps();
        });

        DB::table('roles')->insert([
            ['power' => 10, 'name' => 'user', 'created_at' => now(), 'updated_at' => now()],
            ['power' => 100, 'name' => 'admin', 'created_at' => now(), 'updated_at' => now()],
            ['power' => 1000, 'name' => 'superadmin', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
