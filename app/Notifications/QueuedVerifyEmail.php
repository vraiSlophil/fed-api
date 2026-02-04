<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Throwable;

class QueuedVerifyEmail extends VerifyEmail implements ShouldQueue
{
    public $tries = 3;

    public function toMail($notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Verify Email Address')
            ->line('Please click the button below to verify your email address.')
            ->action('Verify Email Address', $verificationUrl)
            ->line('If you did not create an account, no further action is required.');
    }

    public function viaQueues(): array
    {
        return ['mail' => config('queue.mail_queues.verification', 'emails-verification')];
    }

    protected function verificationUrl($notifiable): string
    {
        $signedUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(config('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
            false
        );

        $frontendBase = rtrim((string) config('app.frontend_url'), '/');
        $path = (string) config('app.frontend_verify_email_path', '/verify-email');
        $frontendPath = '/' . ltrim($path, '/');
        $query = parse_url($signedUrl, PHP_URL_QUERY);

        return $query ? $frontendBase . $frontendPath . '?' . $query : $frontendBase . $frontendPath;
    }

    public function failed($notifiable, Throwable $e): void
    {
        Log::error('Email verification notification failed', [
            'user_id' => $notifiable?->getKey(),
            'error' => $e->getMessage(),
        ]);
    }
}
