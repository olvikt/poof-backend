<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WelcomeToPoof extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user)
    {
    }

    public function build(): self
    {
        return $this->subject('Ласкаво просимо до POOF 🚀')
            ->view('emails.welcome-poof', [
                'user' => $this->user,
            ]);
    }
}
