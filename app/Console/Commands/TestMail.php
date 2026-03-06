<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestMail extends Command
{
    protected $signature = 'mail:test';

    protected $description = 'Send a test email via configured SMTP';

    public function handle(): int
    {
        Mail::raw('POOF SMTP test', function ($msg): void {
            $msg->to('admin@poof.com.ua')->subject('POOF SMTP test');
        });

        $this->info('Test email sent to admin@poof.com.ua');

        return self::SUCCESS;
    }
}
