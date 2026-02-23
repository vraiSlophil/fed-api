<?php

use App\Models\Auth\User;
use App\Models\Billing\Plan;
use App\Models\Billing\UserSubscription;

it('never returns a trialing subscription from another user', function () {
    $plan = Plan::query()->firstOrFail();
    $targetUser = User::factory()->create();
    $otherUser = User::factory()->create();

    $targetSubscription = UserSubscription::query()->create([
        'user_id' => $targetUser->user_id,
        'plan_id' => $plan->plan_id,
        'status' => 'active',
        'started_at' => now()->subDays(2),
    ]);

    UserSubscription::query()->create([
        'user_id' => $otherUser->user_id,
        'plan_id' => $plan->plan_id,
        'status' => 'trialing',
        'started_at' => now()->subDay(),
    ]);

    $active = $targetUser->fresh()->activeSubscription()->first();

    expect($active)->not->toBeNull()
        ->and($active->subscription_id)->toBe($targetSubscription->subscription_id)
        ->and($active->user_id)->toBe($targetUser->user_id);
});

it('returns the latest active or trialing subscription for the same user', function () {
    $plan = Plan::query()->firstOrFail();
    $user = User::factory()->create();

    UserSubscription::query()->create([
        'user_id' => $user->user_id,
        'plan_id' => $plan->plan_id,
        'status' => 'active',
        'started_at' => now()->subDays(3),
    ]);

    $latestTrialing = UserSubscription::query()->create([
        'user_id' => $user->user_id,
        'plan_id' => $plan->plan_id,
        'status' => 'trialing',
        'started_at' => now()->subDay(),
    ]);

    UserSubscription::query()->create([
        'user_id' => $user->user_id,
        'plan_id' => $plan->plan_id,
        'status' => 'canceled',
        'started_at' => now(),
    ]);

    $active = $user->fresh()->activeSubscription()->first();

    expect($active)->not->toBeNull()
        ->and($active->subscription_id)->toBe($latestTrialing->subscription_id)
        ->and($active->status)->toBe('trialing');
});
