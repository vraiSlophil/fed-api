<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class QueuedResetPassword extends ResetPassword implements ShouldQueue
{
    public function viaQueues(): array
    {
        return ['mail' => config('queue.mail_queues.reset', 'emails-password-reset')];
    }

    public function failed(Throwable $e): void
    {
        Log::error('Password reset notification failed', [
            'error' => $e->getMessage(),
        ]);
    }
}
