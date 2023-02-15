<?php

namespace jdavidbakr\MailTracker\Listeners;

use Illuminate\Mail\Events\MessageSending;
use jdavidbakr\MailTracker\Contracts\MailerResolver;
use jdavidbakr\MailTracker\Contracts\TrackerCreator;
use jdavidbakr\MailTracker\Exceptions\DriverNotResolvable;

class MessageSendingListener
{
    public function __construct(
        protected MailerResolver $resolver,
        protected TrackerCreator $trackerCreator,
    ) {
    }

    /**
     * Processes an email prior to sending
     *
     * @throws DriverNotResolvable
     */
    public function handle(MessageSending $event)
    {
        $driver = $this->resolver->resolve($event->data);

        if($driver === null) {
            throw new DriverNotResolvable('The mail driver could not be resolved');
        }

        $this->trackerCreator->create(
            $event->message,
            $driver,
        );
    }
}
