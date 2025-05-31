<?php

namespace App\Providers;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
