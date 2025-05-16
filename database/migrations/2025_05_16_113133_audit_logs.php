<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('auditable_type', 100);
            $table->binary('auditable_id', 16);
            $table->dateTime('changed_at');
            $table->json('data');
            $table->index(['auditable_type', 'auditable_id']);
            // index virtuel MariaDB
            $table->index(\DB::raw("((data->>'$.meta.action'))"), 'idx_action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
