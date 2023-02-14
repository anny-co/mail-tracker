<?php

namespace jdavidbakr\MailTracker\Jobs;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use jdavidbakr\MailTracker\EmailsPurger;

class PurgeSentEmailsJob implements ShouldQueue, ShouldBeUnique
{
    public function handle(EmailsPurger $purger)
    {
        $expireDays = config('mail-tracker.expire-days');
        if (! $expireDays) {
            return;
        }

        $purger->purge($expireDays);
    }
}
