<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('playgrounds', function (Blueprint $table) {
            $table->uuid('playground_id')->primary();
            $table->uuid('user_id');
            $table->string('name', 120);
            $table->string('slug', 140)->nullable();
            $table->string('icon', 50)->nullable();
            $table->char('color', 7)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
            $table->unique(['user_id','slug']);
            $table->index('user_id');
        });
    }
    public function down(): void {
        Schema::dropIfExists('playgrounds');
    }
};