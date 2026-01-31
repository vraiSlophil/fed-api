<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_dependencies', function (Blueprint $table) {
            $table->uuid('dependency_id')->primary();
            $table->uuid('from_task_id');
            $table->uuid('to_task_id');
            $table->enum('type', ['blocking', 'sequential', 'soft'])->default('blocking');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('from_task_id')->references('task_id')->on('tasks')->cascadeOnDelete();
            $table->foreign('to_task_id')->references('task_id')->on('tasks')->cascadeOnDelete();

            $table->unique(['from_task_id', 'to_task_id', 'type']);
            $table->index('from_task_id');
            $table->index('to_task_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_dependencies');
    }
};
