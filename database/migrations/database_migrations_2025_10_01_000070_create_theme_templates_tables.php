<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('theme_templates', function (Blueprint $table) {
            $table->uuid('template_id')->primary();
            $table->uuid('user_id');
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->enum('visibility', ['private', 'shared', 'public'])->default('private');
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
            $table->index(['user_id', 'visibility']);
        });

        Schema::create('theme_template_items', function (Blueprint $table) {
            $table->uuid('item_id')->primary();
            $table->uuid('template_id');
            $table->integer('position');
            $table->string('title', 200);
            $table->text('default_description')->nullable();
            $table->enum('default_status', ['todo', 'in_progress', 'done'])->nullable();
            $table->json('default_metadata')->nullable();
            $table->timestamps();

            $table->foreign('template_id')->references('template_id')->on('theme_templates')->cascadeOnDelete();
            $table->unique(['template_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_template_items');
        Schema::dropIfExists('theme_templates');
    }
};
