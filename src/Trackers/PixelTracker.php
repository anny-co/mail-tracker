<?php

namespace jdavidbakr\MailTracker\Trackers;

use Illuminate\Support\Str;
use jdavidbakr\MailTracker\Contracts\Tracker;

class PixelTracker implements Tracker
{
    public function convert(string $html, string $hash): string
    {
        // Append the tracking url
        $trackingPixel = '<img border=0 width=1 alt="" height=1 src="'.route('mailTracker_t', [$hash]).'" />';

        $linebreak = app(Str::class)->random(32);

        $html = str_replace("\n", $linebreak, $html);

        if (preg_match('/^(.*<body[^>]*>)(.*)$/', $html, $matches)) {
            $html = $matches[1].$matches[2].$trackingPixel;
        } else {
            $html = $html.$trackingPixel;
        }
        $html = str_replace($linebreak, "\n", $html);

        return $html;
    }
}
