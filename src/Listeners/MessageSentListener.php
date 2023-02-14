<?php

namespace jdavidbakr\MailTracker\Listeners;

use Illuminate\Mail\Events\MessageSent;
use jdavidbakr\MailTracker\Contracts\MailTrackerDriver;
use jdavidbakr\MailTracker\MailTracker;
use jdavidbakr\MailTracker\MailTrackerManager;

class MessageSentListener
{


    public function __construct(protected MailTrackerManager $manager)
    {
    }

    public function handle(MessageSent $event)
    {
        $sentMessage = $event->sent;
        $headers = $sentMessage->getOriginalMessage()->getHeaders();
        $hash = optional($headers->get('X-Mailer-Hash'))->getBody();
        $sentEmail = MailTracker::sentEmailModel()->newQuery()->where('hash', $hash)->first();

        if(!$sentEmail){
            return;
        }

        // Identify the driver the message was sent with
        /** @var MailTrackerDriver $driver */
        $driver = $this->manager->driver($sentEmail->mailer);

        $messageId = $driver->resolveMessageId($sentMessage);

        if($messageId === null) {
            $messageId = $sentMessage->getMessageId();
        }

        $sentEmail->message_id = $messageId;
        $sentEmail->save();
    }
}