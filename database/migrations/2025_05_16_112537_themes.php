<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('themes', function (Blueprint $table) {
            $table->binaryUuid();
            $table->binary('owner_id', 16);
            $table->string('title', 150);
            $table->char('color', 7);
            $table->timestamps();

            $table->foreign('owner_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index('owner_id');
            $table->fullText('title');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('themes');
    }
};
