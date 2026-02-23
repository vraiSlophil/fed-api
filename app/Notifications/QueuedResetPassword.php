<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class QueuedResetPassword extends ResetPassword implements ShouldQueue
{
    /**
     * Define queue names per notification channel.
     *
     * @return array Mapping of notification channels to queue names.
     */
    public function viaQueues(): array
    {
        return ['mail' => config('queue.mail_queues.reset', 'emails-password-reset')];
    }

    /**
     * Handle queue failure callback logic.
     *
     * @param  Throwable  $e  Exception captured by the failure callback.
     * @return void No return value.
     */
    public function failed(Throwable $e): void
    {
        Log::error('Password reset notification failed', [
            'error' => $e->getMessage(),
        ]);
    }
}
