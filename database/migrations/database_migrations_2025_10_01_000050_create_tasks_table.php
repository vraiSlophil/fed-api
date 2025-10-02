<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::create('tasks', function (Blueprint $table) {
            $table->uuid('task_id')
                ->default(DB::raw('gen_random_uuid()'));
            $table->primary('task_id'); // Séparé explicitement
            $table->uuid('theme_id');
            $table->uuid('user_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', ['todo','in_progress','done'])->default('todo');
            $table->integer('position')->nullable();
            $table->smallInteger('priority')->default(0);
            $table->timestamp('due_at')->nullable();
            $table->uuid('parent_task_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->foreign('theme_id')->references('theme_id')->on('themes')->cascadeOnDelete();
            $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
            $table->foreign('parent_task_id')->references('task_id')->on('tasks')->cascadeOnDelete();

            $table->index(['theme_id','status']);
            $table->index(['theme_id','position']);
            $table->index('user_id');
        });
    }

    public function down(): void {
        Schema::dropIfExists('tasks');
    }
};
