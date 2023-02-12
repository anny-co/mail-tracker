<?php

namespace jdavidbakr\MailTracker;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use jdavidbakr\MailTracker\Contracts\MailerResolver;
use jdavidbakr\MailTracker\Contracts\SentEmailModel;

class MailTrackerMailerResolver implements MailerResolver
{

    public function resolve(array $eventData = []): string|null
    {
        // 1. look at event data
        if($mailer = Arr::get($eventData, 'mailer')) {
            return $mailer;
        }

        // 2. look at mailer tracker
        if($mailer = MailTracker::$mailer){
            return $mailer;
        }

        // 3. use configures mail drive
        // We assume here that the developer is only using one configured driver.
        // When using multiple driver for different mails this could probably
        // set the wrong driver.
        $mailer = config('mail.driver') ?? config('mail.default');

        return $mailer;
    }
}