<?php

namespace jdavidbakr\MailTracker\Drivers\Mailgun\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use jdavidbakr\MailTracker\Events\PermanentBouncedMessageEvent;
use jdavidbakr\MailTracker\Events\TransientBouncedMessageEvent;
use jdavidbakr\MailTracker\MailTracker;

class MailgunRecordBounceJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

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
            $currentCodes = [];
            if ($meta->has('failures')) {
                $currentCodes = $meta->get('failures');
            }
            $currentCodes[] = ['emailAddress' => Arr::get($this->eventData, 'recipient')];
            $meta->put('failures', $currentCodes);
            $meta->put('success', false);
            $meta->put('mailgun_message_bounce', $this->eventData); // append the full message received from Mailgun to the 'meta' field
            $sentEmail->meta = $meta;
            $sentEmail->save();

            if (Arr::has($this->eventData, 'reject')) {
                // handle rejection
                $this->permanentBounce($sentEmail);
            } elseif (Arr::get($this->eventData, 'severity') === 'permanent') {
                $this->permanentBounce($sentEmail);
            } else {
                $this->transientBounce($sentEmail);
            }
        }
    }

    protected function permanentBounce($sentEmail)
    {
        Event::dispatch(new PermanentBouncedMessageEvent(Arr::get($this->eventData, 'recipient'), $sentEmail));
    }

    protected function transientBounce($sentEmail)
    {
        Event::dispatch(new TransientBouncedMessageEvent(
            Arr::get($this->eventData, 'recipient'),
            Arr::get($this->eventData, 'severity'),
            Arr::get($this->eventData, 'delivery-status.message').' '.Arr::get($this->eventData, 'delivery-status.description'),
            $sentEmail
        ));
    }
}
