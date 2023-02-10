<?php

namespace jdavidbakr\MailTracker\Traits;

use jdavidbakr\MailTracker\MailTracker;

trait HasSentEmails
{
    public function sentEmails()
    {
        return $this->morphMany(MailTracker::$sentEmailModel, 'mailable');
    }
}
