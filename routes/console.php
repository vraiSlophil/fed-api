<?php

use App\Domain\Invitations\Services\InvitationService;
use App\Models\Auth\RevokedRefreshToken;
use App\Models\Invitations\Invitation;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

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

Artisan::command('invitations:expire', function (InvitationService $invitationService) {
    $processed = 0;

    Invitation::query()
        ->where('status', 'pending')
        ->whereNotNull('expires_at')
        ->where('expires_at', '<=', now())
        ->orderBy('expires_at')
        ->chunk(100, function ($invitations) use ($invitationService, &$processed) {
            foreach ($invitations as $invitation) {
                if (! $invitation instanceof Invitation) {
                    continue;
                }

                $invitationService->expireInvitation($invitation);
                $processed++;
            }
        });

    $this->info("Processed {$processed} expired invitations.");
})->purpose('Expire pending invitations and notify inviters');

Schedule::command('auth:prune-revoked-refresh')->daily();
Schedule::command('invitations:expire')->daily();
