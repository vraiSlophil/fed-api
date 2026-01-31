<?php

use App\Models\RevokedRefreshToken;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('auth:prune-revoked-refresh', function () {
    $deleted = RevokedRefreshToken::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();

    $this->info("Pruned {$deleted} revoked refresh tokens.");
})->purpose('Prune revoked refresh tokens');

Schedule::command('auth:prune-revoked-refresh')->daily();
