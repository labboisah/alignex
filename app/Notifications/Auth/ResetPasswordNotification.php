<?php

namespace App\Notifications\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Config;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $expire = Config::get('auth.passwords.'.Config::get('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('Reset your AlignEx password')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('We received a request to reset the password for your AlignEx portal account.')
            ->action('Reset Password', $this->resetUrl($notifiable))
            ->line('This password reset link will expire in '.$expire.' minutes.')
            ->line('If you did not request a password reset, you can safely ignore this email.');
    }

    private function resetUrl(object $notifiable): string
    {
        return route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], absolute: true);
    }
}
