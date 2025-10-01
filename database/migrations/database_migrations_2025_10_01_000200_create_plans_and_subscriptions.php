<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void {
        Schema::create('plans', function (Blueprint $table) {
            $table->uuid('plan_id')->primary();
            $table->string('code', 50)->unique();   // freemium, pro, team...
            $table->string('name', 100);
            $table->decimal('monthly_price', 8, 2)->nullable();
            $table->decimal('yearly_price', 8, 2)->nullable();
            $table->json('features');               // JSON de quotas et flags
            $table->timestamps();
        });

        Schema::create('user_subscriptions', function (Blueprint $table) {
            $table->uuid('subscription_id')->primary();
            $table->uuid('user_id');
            $table->uuid('plan_id');
            $table->enum('status', ['active','trialing','canceled','expired'])->default('active');
            $table->timestamp('started_at');
            $table->timestamp('renews_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('cancellation_requested_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
            $table->foreign('plan_id')->references('plan_id')->on('plans')->restrictOnDelete();
            $table->index(['user_id','status']);
        });

        // Seed basique
        DB::table('plans')->insert([
            [
                'plan_id' => (string) Str::uuid(),
                'code' => 'freemium',
                'name' => 'Free',
                'monthly_price' => null,
                'yearly_price' => null,
                'features' => json_encode([
                    'max_playgrounds' => 1,
                    'max_themes' => 50,
                    'max_tasks' => 1000,
                    'max_reminders' => 20,
                    'dependencies' => true,
                    'templates' => true,
                    'reminders' => true
                ]),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'plan_id' => (string) Str::uuid(),
                'code' => 'pro',
                'name' => 'Pro',
                'monthly_price' => 9.99,
                'yearly_price' => 99.00,
                'features' => json_encode([
                    'max_playgrounds' => 10,
                    'max_themes' => 500,
                    'max_tasks' => 50000,
                    'max_reminders' => 500,
                    'dependencies' => true,
                    'templates' => true,
                    'reminders' => true,
                    'priority_support' => true
                ]),
                'created_at' => now(),
                'updated_at' => now()
            ],
        ]);
    }

    public function down(): void {
        Schema::dropIfExists('user_subscriptions');
        Schema::dropIfExists('plans');
    }
};
