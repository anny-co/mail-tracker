<?php

namespace jdavidbakr\MailTracker\Contracts;

interface Tracker
{
    public function convert(string $html, string $hash): string;
}
