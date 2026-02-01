<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->uuid('invitation_id')->primary();
            $table->uuid('inviter_user_id');
            $table->uuid('invitee_user_id');
            $table->string('invitable_type');
            $table->uuid('invitable_id');
            $table->json('payload')->nullable();
            $table->enum('status', ['pending', 'accepted', 'declined', 'expired'])->default('pending');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['invitee_user_id', 'invitable_type', 'invitable_id']);
            $table->index(['invitable_type', 'invitable_id']);
            $table->index('invitee_user_id');
            $table->index('inviter_user_id');

            $table->foreign('inviter_user_id')->references('user_id')->on('users')->cascadeOnDelete();
            $table->foreign('invitee_user_id')->references('user_id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
