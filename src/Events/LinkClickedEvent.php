<?php

namespace jdavidbakr\MailTracker\Events;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\SerializesModels;
use jdavidbakr\MailTracker\Contracts\SentEmailModel;

class LinkClickedEvent implements ShouldQueue
{
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param Model|SentEmailModel $sentEmail
     * @param string $ipAddress
     * @param string $linkUrl
     */
    public function __construct(
        public Model|SentEmailModel $sentEmail,
        public string               $ipAddress,
        public string               $linkUrl)
    {
    }
}
