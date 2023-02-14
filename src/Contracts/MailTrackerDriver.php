<?php

declare(strict_types=1);

namespace jdavidbakr\MailTracker\Contracts;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Mail\SentMessage;

interface MailTrackerDriver
{
    public function callback(Request $request): Response;

    public function resolveMessageId(SentMessage $message): ?string;
}
