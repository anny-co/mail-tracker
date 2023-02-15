<?php

namespace jdavidbakr\MailTracker\Drivers\Mailgun\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use jdavidbakr\MailTracker\Events\ComplaintMessageEvent;
use jdavidbakr\MailTracker\MailTracker;

class MailgunRecordComplaintJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * See message structure
     *
     * @docs https://documentation.mailgun.com/en/latest/api-events.html#event-structure
     */
    public function __construct(public array $eventData)
    {
    }

    public function handle()
    {
        $messageId = Arr::get($this->eventData, 'message.headers.message-id');
        $sentEmail = MailTracker::sentEmailModel()
            ->newQuery()
            ->where('message_id', $messageId)
            ->first();

        if ($sentEmail) {
            $meta = collect($sentEmail->meta);
            $meta->put('complaint', true);
            $meta->put('success', false);
            $meta->put('complaint_time', Arr::get($this->eventData, 'timestamp'));
            $meta->put('mailgun_message_complaint', $this->eventData); // append the full message received from Mailgun to the 'meta' field
            $sentEmail->meta = $meta;
            $sentEmail->save();

            Event::dispatch(new ComplaintMessageEvent(Arr::get($this->eventData, 'recipient'), $sentEmail));
        }
    }
}
