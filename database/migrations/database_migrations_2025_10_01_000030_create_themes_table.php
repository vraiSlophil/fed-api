<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('themes', function (Blueprint $table) {
            $table->uuid('theme_id')->primary();
            $table->uuid('owner_id');
            $table->uuid('playground_id');
            $table->string('title', 150);
            $table->char('color', 7);
            $table->enum('visibility', ['private','shared','public'])->default('private');
            $table->timestamps();

            $table->foreign('owner_id')->references('user_id')->on('users')->cascadeOnDelete();
            $table->foreign('playground_id')->references('playground_id')->on('playgrounds')->cascadeOnDelete();
            $table->index(['playground_id','visibility']);
            $table->index('owner_id');
        });
    }
    public function down(): void {
        Schema::dropIfExists('themes');
    }
};