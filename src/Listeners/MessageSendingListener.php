<?php

namespace jdavidbakr\MailTracker\Listeners;

use Illuminate\Mail\Events\MessageSending;
use jdavidbakr\MailTracker\Contracts\MailerResolver;
use jdavidbakr\MailTracker\Contracts\TrackerCreator;

class MessageSendingListener
{

    public function __construct(
        protected MailerResolver $resolver,
        protected TrackerCreator $trackerCreator,
    )
    {
    }

    public function handle(MessageSending $event)
    {
        $this->trackerCreator->create(
            $event->message,
            $this->resolver->resolve($event->data)
        );
    }

}