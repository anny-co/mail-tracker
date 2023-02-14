<?php

namespace jdavidbakr\MailTracker\Contracts;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Symfony\Component\Mime\Header\Headers;

interface SentEmailModel
{
    public function getConnectionName();

    public function urlClicks();

    public function mailable();

    public function mailer(): Attribute;

    public function getAllHeaders();

    public function getHeader(string $key);

    public function fillContent(string $originalHtml, string $hash): static;

    public function fillMailableModelFromHeaders(Headers $headers): static;
}
