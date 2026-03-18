<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordPoofNotification extends Notification
{
    use Queueable;

    public const SUBJECT = 'Скидання пароля в POOF';

    public function __construct(
        public string $token,
    ) {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subjectLine())
            ->view('emails.reset-password-poof', [
                'user' => $notifiable,
                'resetUrl' => $this->resetUrl($notifiable),
                'expireMinutes' => $this->expirationMinutes(),
                'subject' => $this->subjectLine(),
            ]);
    }

    public function subjectLine(): string
    {
        return self::SUBJECT;
    }

    public function resetUrl(CanResetPassword $notifiable): string
    {
        return url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));
    }

    public function expirationMinutes(): int
    {
        return (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire');
    }
}
