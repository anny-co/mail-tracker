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
use jdavidbakr\MailTracker\Events\ViewEmailEvent;

class RecordTrackingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Model|SentEmailModel $sentEmail,
        public string $ipAddress)
    {
    }

    public function handle()
    {
        $this->sentEmail->opens++;
        $this->sentEmail->save();
        Event::dispatch(new ViewEmailEvent($this->sentEmail, $this->ipAddress));
    }
}
