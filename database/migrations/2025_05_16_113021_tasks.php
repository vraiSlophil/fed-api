<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->binaryUuid();
            $table->binary('theme_id', 16);
            $table->binary('creator_id', 16)->nullable();
            $table->string('title');
            $table->enum('status', ['todo', 'doing', 'done'])->default('todo');
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->foreign('theme_id')->references('id')->on('themes')->cascadeOnDelete();
            $table->foreign('creator_id')->references('id')->on('users')->nullOnDelete();

            $table->index(['theme_id', 'status', 'created_at']);
            $table->index('archived_at');
            $table->fullText('title');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
