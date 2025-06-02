<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Colonne BINARY(16) + clé primaire
        Blueprint::macro('binaryUuid', function (string $column = 'id') {
            /** @var Blueprint $this */
            $this->binary($column, 16)->primary();
        });
    }
}
