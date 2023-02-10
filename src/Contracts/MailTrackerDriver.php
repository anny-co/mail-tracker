<?php
declare(strict_types=1);

namespace jdavidbakr\MailTracker\Contracts;

use Illuminate\Http\Request;
use Illuminate\Mail\SentMessage;

interface MailTrackerDriver
{

    public function __invoke();

    //public function boot();

    public function callback(Request $request);

    public function resolveMessageId(SentMessage $message) : ?string;

}
