<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('theme_user_permissions', function (Blueprint $table) {
            $table->uuid('theme_id');
            $table->uuid('user_id');
            $table->boolean('can_view')->default(true);
            $table->boolean('can_update_theme')->default(false);
            $table->boolean('can_add_task')->default(false);
            $table->boolean('can_edit_task')->default(false);
            $table->boolean('can_delete_task')->default(false);
            $table->boolean('can_validate_task')->default(false);
            $table->enum('status', ['active', 'revoked'])->default('active');
            $table->timestamp('invited_at')->nullable();

            $table->primary(['theme_id', 'user_id']);
            $table->foreign('theme_id')->references('theme_id')->on('themes')->cascadeOnDelete();
            $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_user_permissions');
    }
};
