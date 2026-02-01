<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class QueuedResetPassword extends ResetPassword implements ShouldQueue
{
    use Queueable;

    public function __construct(string $token)
    {
        parent::__construct($token);
    }

    /**
     * Route the mail channel to the dedicated reset queue.
     */
    public function viaQueues(): array
    {
        return ['mail' => config('queue.mail_queues.reset', 'emails-password-reset')];
    }
}
