<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApiPasswordResetNotification extends Notification
{
    use Queueable;

    public function __construct(public string $token) {}

    /**
     * Mail-only notification carrying just the raw reset token — the mobile
     * app collects it and submits it back via POST /api/v1/auth/reset-password.
     * Deliberately has no web URL / createUrlUsing, since there is no web
     * password-reset flow for this API-only feature.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Password Reset Code')
            ->line('You requested a password reset.')
            ->line('Enter this code in the app to reset your password:')
            ->line($this->token)
            ->line('If you did not request a password reset, no further action is required.');
    }
}
