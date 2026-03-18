<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordPoofNotification extends ResetPassword
{
    use Queueable;

    public const SUBJECT = 'Скидання пароля в POOF';

    public function toMail(object $notifiable): MailMessage
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

    public function resetUrl(object $notifiable): string
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
