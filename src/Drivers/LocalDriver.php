<?php

namespace jdavidbakr\MailTracker\Drivers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Mail\SentMessage;
use jdavidbakr\MailTracker\Contracts\MailTrackerDriver;

class LocalDriver implements MailTrackerDriver
{

    public function callback(Request $request) : Response
    {
        return response('', 204);
    }

    public function resolveMessageId(SentMessage $message): ?string
    {
        return $message->getMessageId();
    }


}
