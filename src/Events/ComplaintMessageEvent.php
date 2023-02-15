<?php

namespace jdavidbakr\MailTracker\Events;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\SerializesModels;
use jdavidbakr\MailTracker\Contracts\SentEmailModel;

class ComplaintMessageEvent implements ShouldQueue
{
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  string  $emailAddress
     * @param  Model|SentEmailModel|null  $sentEmail
     */
    public function __construct(
        public string                    $emailAddress,
        public Model|SentEmailModel|null $sentEmail = null)
    {
    }
}
