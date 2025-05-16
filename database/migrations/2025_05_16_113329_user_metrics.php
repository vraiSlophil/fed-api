<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_metrics', function (Blueprint $table) {
            $table->binary('user_id', 16)->primary();
            $table->unsignedInteger('tasks_created')->default(0);
            $table->unsignedInteger('tasks_done')->default(0);
            $table->unsignedInteger('streak')->default(0);
            $table->timestamp('updated_at')->useCurrentOnUpdate()->useCurrent();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_metrics');
    }
};
