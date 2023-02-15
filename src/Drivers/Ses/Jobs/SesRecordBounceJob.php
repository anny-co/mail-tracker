<?php

namespace jdavidbakr\MailTracker\Drivers\Ses\Jobs;

use Aws\Sns\Message as SNSMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Event;
use jdavidbakr\MailTracker\Contracts\SentEmailModel;
use jdavidbakr\MailTracker\Events\PermanentBouncedMessageEvent;
use jdavidbakr\MailTracker\Events\TransientBouncedMessageEvent;
use jdavidbakr\MailTracker\MailTracker;
use stdClass;

class SesRecordBounceJob implements ShouldQueue
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
            $currentCode = [];
            if ($meta->has('failures')) {
                $currentCode = $meta->get('failures');
            }
            foreach ($this->message->bounce->bouncedRecipients as $failure_details) {
                $currentCode[] = $failure_details;
            }
            $meta->put('failures', $currentCode);
            $meta->put('success', false);
            $meta->put('sns_message_bounce', $this->message); // append the full message received from SNS to the 'meta' field
            $sentEmail->meta = $meta;
            $sentEmail->save();

            if ($this->message->bounce->bounceType == 'Permanent') {
                $this->permanentBounce($sentEmail);
            } else {
                $this->transientBounce($sentEmail);
            }
        }
    }

    protected function permanentBounce(Model|SentEmailModel $sendEmail): void
    {
        foreach ($this->message->bounce->bouncedRecipients as $recipient) {
            Event::dispatch(new PermanentBouncedMessageEvent($recipient->emailAddress, $sendEmail));
        }
    }

    protected function transientBounce(Model|SentEmailModel $sentEmail): void
    {
        foreach ($this->message->bounce->bouncedRecipients as $recipient) {
            Event::dispatch(new TransientBouncedMessageEvent(
                $recipient->emailAddress,
                $this->message->bounce->bounceSubType,
                optional($recipient)->diagnosticCode ?: '',
                $sentEmail
            ));
        }
    }
}
