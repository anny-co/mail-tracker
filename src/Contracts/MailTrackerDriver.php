<?php

declare(strict_types=1);

namespace jdavidbakr\MailTracker\Contracts;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Mail\SentMessage;

interface MailTrackerDriver
{

    /**
     * Handles the callback from a third-party mailer service (for example, bounce notifications)
     *
     * @param Request $request
     * @return Response
     */
    public function callback(Request $request): Response;

    /**
     * Resolves a message identifier
     *
     * @param SentMessage $message
     * @return string|null
     */
    public function resolveMessageId(SentMessage $message): ?string;
}
