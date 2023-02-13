<?php

namespace jdavidbakr\MailTracker\Console\Commands;

use Illuminate\Console\Command;
use jdavidbakr\MailTracker\EmailsPurger;

class PurgeSentEmailsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mail-tracker:purge
                            {--expire-days= : Define the expiry, we use the config value by default}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purge old sent emails';

    /**
     * Execute the console command.
     */
    public function handle(EmailsPurger $purger): void
    {
        $expireDays = config('mail-tracker.expire-days');

        if ($this->option('expire-days')) {
            $expireDays = $this->option('expire-days');
        }

        $purger->purge($expireDays);
    }
}
