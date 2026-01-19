<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('auditable_type', 100);
            $table->uuid('auditable_id');
            $table->timestamp('changed_at');
            $table->json('data');
            $table->string('meta_action')->nullable(); // sera générée après

            $table->index(['auditable_type', 'auditable_id']);
            $table->index('changed_at');
        });

        // Colonne générée (Postgres)
        // On reconstruit via expression; si besoin portable, laisser logique applicative
        DB::statement('ALTER TABLE audit_logs ALTER COLUMN meta_action DROP DEFAULT;');
        DB::statement("UPDATE audit_logs SET meta_action = data->'meta'->>'action';");
        DB::statement('ALTER TABLE audit_logs ALTER COLUMN meta_action SET DATA TYPE varchar(150);');
        DB::statement('CREATE INDEX audit_logs_meta_action_idx ON audit_logs(meta_action);');
        // Option: trigger pour maintenir meta_action si tu veux mutation côté DB.
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
