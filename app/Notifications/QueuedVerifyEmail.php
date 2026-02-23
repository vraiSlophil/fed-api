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

    /**
     * Build the email-verification message for the notifiable user.
     *
     * @param  mixed  $notifiable  Notifiable model exposing email-verification helper methods.
     * @return MailMessage MailMessage instance returned after successful execution.
     */
    public function toMail($notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Verify Email Address')
            ->line('Please click the button below to verify your email address.')
            ->action('Verify Email Address', $verificationUrl)
            ->line('If you did not create an account, no further action is required.');
    }

    /**
     * Define queue names per notification channel.
     *
     * @return array Mapping of notification channels to queue names.
     */
    public function viaQueues(): array
    {
        return ['mail' => config('queue.mail_queues.verification', 'emails-verification')];
    }

    /**
     * Build the frontend verification URL from a temporary signed backend route.
     *
     * @param  mixed  $notifiable  Notifiable model exposing identifier and verification email.
     * @return string Frontend verification URL that preserves the signed query parameters.
     */
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
        $frontendPath = '/'.ltrim($path, '/');
        $query = parse_url($signedUrl, PHP_URL_QUERY);

        return $query ? $frontendBase.$frontendPath.'?'.$query : $frontendBase.$frontendPath;
    }

    /**
     * Handle queue failure callback logic.
     *
     * @param  Throwable  $e  Exception captured by the failure callback.
     * @return void No return value.
     */
    public function failed(Throwable $e): void
    {
        Log::error('Email verification notification failed', [
            'error' => $e->getMessage(),
        ]);
    }
}
