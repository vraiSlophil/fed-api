<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('user_metrics', function (Blueprint $table) {
            $table->uuid('user_id')->primary();
            $table->unsignedInteger('total_themes_created')->default(0);
            $table->unsignedInteger('total_tasks_created')->default(0);
            $table->unsignedInteger('total_tasks_completed')->default(0);
            $table->unsignedInteger('current_streak_days')->default(0);
            $table->unsignedInteger('longest_streak_days')->default(0);
            $table->date('last_activity_date')->nullable();
            $table->unsignedInteger('themes_created_this_week')->default(0);
            $table->unsignedInteger('themes_created_last_week')->default(0);
            $table->unsignedInteger('tasks_created_this_week')->default(0);
            $table->unsignedInteger('tasks_created_last_week')->default(0);
            $table->unsignedInteger('tasks_completed_this_week')->default(0);
            $table->unsignedInteger('tasks_completed_last_week')->default(0);
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
            $table->index('last_activity_date');
        });
    }
    public function down(): void {
        Schema::dropIfExists('user_metrics');
    }
};