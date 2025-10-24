<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendTestMail extends Command
{
    protected $signature = 'mail:test {to} {--subject=Office 365 relay test} {--message=This is a test email from pinescore engine.} {--from=}';

    protected $description = 'Send a test email using the current mail configuration';

    public function handle(): int
    {
        $to = (string) $this->argument('to');
        $subject = (string) $this->option('subject');
        $message = (string) $this->option('message');

        try {
            $from = $this->option('from');

            Mail::raw($message, function ($m) use ($to, $subject, $from) {
                if (!empty($from)) {
                    $m->from($from);
                }
                $m->to($to)->subject($subject);
            });

            $this->info('Test email sent to ' . $to);
            return 0;
        } catch (\Throwable $e) {
            $this->error('Failed sending test email: ' . $e->getMessage());
            return 1;
        }
    }
}
