<?php

namespace jdavidbakr\MailTracker\Events;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\SerializesModels;
use jdavidbakr\MailTracker\Contracts\SentEmailModel;

class TransientBouncedMessageEvent implements ShouldQueue
{
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param string $emailAddress
     * @param string $bounceSubType
     * @param string $diagnosticCode
     * @param Model|SentEmailModel|null $sentEmail $sent_email
     */
    public function __construct(
        public string                    $emailAddress,
        public string                    $bounceSubType,
        public string                    $diagnosticCode,
        public Model|SentEmailModel|null $sentEmail = null
    )
    {
    }
}
