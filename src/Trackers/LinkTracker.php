<?php

namespace jdavidbakr\MailTracker\Trackers;

use jdavidbakr\MailTracker\Contracts\Tracker;

class LinkTracker implements Tracker
{

    public function convert(string $html, string $hash): string
    {
        return preg_replace_callback(
            "/(<a[^>]*href=[\"])([^\"]*)/",
            function ($matches) use ($hash) {
                return $this->injectLinkCallback($matches, $hash);
            },
            $html
        );
    }

    protected function injectLinkCallback(array $matches, string $hash)
    {
        if (empty($matches[2])) {
            $url = app()->make('url')->to('/');
        } else {
            $url = str_replace('&amp;', '&', $matches[2]);
        }

        return $matches[1] . route(
                'mailTracker_n',
                [
                    'l' => $url,
                    'h' => $hash
                ]
            );
    }
}