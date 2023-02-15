<?php

namespace jdavidbakr\MailTracker\Events;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\SerializesModels;
use jdavidbakr\MailTracker\Contracts\SentEmailModel;

class ViewEmailEvent implements ShouldQueue
{
    use SerializesModels;

    /**
     * @param Model|SentEmailModel $sentEmail
     * @param string $ipAddress
     */
    public function __construct(
        public Model|SentEmailModel $sentEmail,
        public string               $ipAddress)
    {
    }
}
