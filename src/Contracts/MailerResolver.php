<?php

namespace jdavidbakr\MailTracker\Contracts;

interface MailerResolver
{
    public function resolve(array $eventData = []): null|string;
}
