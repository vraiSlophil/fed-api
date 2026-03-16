<?php

use App\Domain\Auth\Services\TokenService;
use App\Models\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

pest()->extend(Tests\TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

function actingAsAccessUser(User $user): User
{
    Sanctum::actingAs($user, [TokenService::ACCESS_ABILITY]);

    return $user;
}
