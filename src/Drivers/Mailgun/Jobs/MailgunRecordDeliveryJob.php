<?php

namespace jdavidbakr\MailTracker\Drivers\Mailgun\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use jdavidbakr\MailTracker\Events\EmailDeliveredEvent;
use jdavidbakr\MailTracker\MailTracker;

class MailgunRecordDeliveryJob implements ShouldQueue
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
            $code = Arr::get($this->eventData, 'delivery-status.code');
            $success = 200 <= $code && $code < 300;
            $smtpResponse = $code.' - '
                .Arr::get($this->eventData, 'delivery-status.message').' '
                .Arr::get($this->eventData, 'delivery-status.description');

            $meta = collect($sentEmail->meta);
            $meta->put('smtpResponse', $smtpResponse);
            $meta->put('success', $success);
            $meta->put('delivered_at', Carbon::createFromTimestamp(Arr::get($this->eventData, 'timestamp'))->toIso8601ZuluString());
            $meta->put('mailgun_message_delivery', $this->eventData); // append the full message received from SNS to the 'meta' field
            $sentEmail->meta = $meta;
            $sentEmail->save();

            Event::dispatch(new EmailDeliveredEvent(Arr::get($this->eventData, 'recipient'), $sentEmail));
        }
    }
}
