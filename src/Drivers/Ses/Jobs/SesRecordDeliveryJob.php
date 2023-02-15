<?php

namespace jdavidbakr\MailTracker\Drivers\Ses\Jobs;

use Aws\Sns\Message as SNSMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Event;
use jdavidbakr\MailTracker\Events\EmailDeliveredEvent;
use jdavidbakr\MailTracker\MailTracker;
use stdClass;

class SesRecordDeliveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public stdClass $message)
    {
    }

    public function handle()
    {
        $sentEmail = MailTracker::sentEmailModel()
            ->newQuery()
            ->where('message_id', $this->message->mail->messageId)
            ->first();

        if ($sentEmail) {
            $meta = collect($sentEmail->meta);
            $meta->put('smtpResponse', $this->message->delivery->smtpResponse);
            $meta->put('success', true);
            $meta->put('delivered_at', $this->message->delivery->timestamp);
            $meta->put('sns_message_delivery', $this->message); // append the full message received from SNS to the 'meta' field
            $sentEmail->meta = $meta;
            $sentEmail->save();

            foreach ($this->message->delivery->recipients as $recipient) {
                Event::dispatch(new EmailDeliveredEvent($recipient, $sentEmail));
            }
        }
    }
}
