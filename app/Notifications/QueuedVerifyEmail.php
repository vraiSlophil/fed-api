<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class QueuedVerifyEmail extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    /**
     * Route the mail channel to the dedicated email queue.
     */
    public function viaQueues(): array
    {
        return ['mail' => config('queue.mail_queue', 'emails')];
    }
}
