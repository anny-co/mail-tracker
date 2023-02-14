<?php

namespace jdavidbakr\MailTracker\Contracts;

use Symfony\Component\Mime\Email;

interface TrackerCreator
{
    public function create(Email $message, string $mailer);
}
