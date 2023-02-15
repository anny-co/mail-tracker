<?php

namespace jdavidbakr\MailTracker\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Event;
use jdavidbakr\MailTracker\Contracts\SentEmailModel;
use jdavidbakr\MailTracker\Events\LinkClickedEvent;
use jdavidbakr\MailTracker\MailTracker;

class RecordLinkClickJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Model|SentEmailModel $sentEmail,
        public string $url,
        public string $ipAddress
    ) {
    }

    public function handle()
    {
        $this->sentEmail->clicks++;
        $this->sentEmail->save();
        $urlClicked = MailTracker::sentEmailUrlClickedModel()
            ->newQuery()
            ->where('url', $this->url)
            ->where('hash', $this->sentEmail->hash)
            ->first();

        if ($urlClicked) {
            $urlClicked->clicks++;
            $urlClicked->save();
        } else {
            MailTracker::sentEmailUrlClickedModel()->newQuery()->create([
                'sent_email_id' => $this->sentEmail->id,
                'url' => $this->url,
                'hash' => $this->sentEmail->hash,
            ]);
        }

        Event::dispatch(new LinkClickedEvent(
            $this->sentEmail,
            $this->ipAddress,
            $this->url,
        ));
    }
}
