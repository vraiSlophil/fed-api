<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('playgrounds', function (Blueprint $table) {
            // Si l'extension pgcrypto est activée (migration préalable), on peut faire:
            // $table->uuid('playground_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('playground_id')->primary();
            $table->uuid('user_id');

            $table->string('name', 120);
            $table->string('slug', 140)->nullable();           // Identifiant lisible dans les URL
            $table->string('icon', 50)->nullable();
            $table->char('color', 7)->nullable();               // Couleur principale
            $table->char('background_color', 7)->nullable();    // Couleur de fond personnalisée
            $table->json('style')->nullable();                  // JSON extensible (ex: {"gradient":["#123","#456"],"layout":"compact"}

            // Prévisualisation (future fonctionnalité)
            $table->string('preview_image_url')->nullable();
            $table->timestamp('preview_updated_at')->nullable();

            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
            $table->unique(['user_id','slug']); // Garantit unicité slug par utilisateur
            $table->index('user_id');
            $table->index(['user_id','is_default']);
        });

        // Index partiel (PostgreSQL) : un seul playground par défaut par utilisateur
        // Ignoré silencieusement si tu es en MySQL (à encapsuler dans un try/catch si multi-SGBD)
        DB::statement("CREATE UNIQUE INDEX uniq_user_default_playground ON playgrounds(user_id) WHERE is_default = true;");
    }

    public function down(): void
    {
        // Supprimer l'index partiel explicitement si nécessaire (PostgreSQL)
        try {
            DB::statement("DROP INDEX IF EXISTS uniq_user_default_playground;");
        } catch (\Throwable $e) {
            // Ignorer si non supporté
        }

        Schema::dropIfExists('playgrounds');
    }
};