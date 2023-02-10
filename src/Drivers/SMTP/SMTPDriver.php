<?php
declare(strict_types=1);

namespace jdavidbakr\MailTracker\Drivers\SMTP;

use Illuminate\Http\Request;
use Illuminate\Mail\SentMessage;
use jdavidbakr\MailTracker\MailTrackerDriverController;

class SMTPDriver extends MailTrackerDriverController
{
    public function __invoke()
    {

    }

    public function callback(Request $request)
    {
        return null;
    }

    public function resolveMessageId(SentMessage $message): ?string
    {
        return $message->getMessageId();
    }


}
