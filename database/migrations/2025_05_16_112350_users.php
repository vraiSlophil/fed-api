<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->binaryUuid();            // id
            $table->string('pseudo', 50)->unique();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('avatar_path')->nullable();
            $table->string('nom')->nullable();
            $table->string('prenom')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->json('settings')->nullable();
            $table->smallInteger('role_power')->default(10);
            $table->timestamps();

            $table->foreign('role_power')->references('power')->on('roles');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
