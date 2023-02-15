<?php

namespace jdavidbakr\MailTracker\Drivers\Ses\Jobs;

use Aws\Sns\Message as SNSMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Event;
use jdavidbakr\MailTracker\Events\ComplaintMessageEvent;
use jdavidbakr\MailTracker\MailTracker;
use stdClass;

class SesRecordComplaintJob implements ShouldQueue
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
            $meta->put('complaint', true);
            $meta->put('success', false);
            $meta->put('complaint_time', $this->message->complaint->timestamp);
            if (! empty($this->message->complaint->complaintFeedbackType)) {
                $meta->put('complaint_type', $this->message->complaint->complaintFeedbackType);
            }
            $meta->put('sns_message_complaint', $this->message); // append the full message received from SNS to the 'meta' field
            $sentEmail->meta = $meta;
            $sentEmail->save();

            foreach ($this->message->complaint->complainedRecipients as $recipient) {
                Event::dispatch(new ComplaintMessageEvent($recipient->emailAddress, $sentEmail));
            }
        }
    }
}
