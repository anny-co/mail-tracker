<?php

namespace jdavidbakr\MailTracker\Contracts;

use Illuminate\Database\Eloquent\Model;
use jdavidbakr\MailTracker\Model\SentEmail;

interface MailerResolver
{

    public function resolve(array $eventData = []): null|string;
}