<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::create('reminders', function (Blueprint $table) {
            $table->uuid('reminder_id')->primary();
            $table->uuid('user_id');
            $table->uuid('task_id')->nullable();
            $table->string('title', 180);
            $table->text('message')->nullable();
            $table->string('timezone', 64);
            $table->timestamp('due_at')->nullable();
            $table->text('rrule')->nullable();
            $table->enum('status', ['active','paused','completed','cancelled'])->default('active');
            $table->integer('occurrences_count')->default(0);
            $table->integer('max_occurrences')->nullable();
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
            $table->foreign('task_id')->references('task_id')->on('tasks')->cascadeOnDelete();

            $table->index(['user_id','status']);
            $table->index('task_id');
            $table->index('next_run_at'); // Option: enlever et créer un index partiel
        });

        // Index partiel (utile en Postgres, ignore sous MySQL)
        DB::statement("CREATE INDEX reminders_active_next_run_idx ON reminders(next_run_at) WHERE status='active';");

        Schema::create('reminder_notifications', function (Blueprint $table) {
            $table->uuid('notification_id')->primary();
            $table->uuid('reminder_id');
            $table->uuid('user_id');
            $table->timestamp('event_time');
            $table->timestamp('delivered_at')->nullable();
            $table->integer('attempts')->default(0);
            $table->text('error')->nullable();
            $table->timestamps();

            $table->foreign('reminder_id')->references('reminder_id')->on('reminders')->cascadeOnDelete();
            $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();

            $table->index(['user_id','delivered_at']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('reminder_notifications');
        Schema::dropIfExists('reminders');
    }
};