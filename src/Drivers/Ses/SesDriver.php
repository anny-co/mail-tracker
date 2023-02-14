<?php

namespace jdavidbakr\MailTracker\Drivers\Ses;

use Aws\Sns\Message as SNSMessage;
use Aws\Sns\MessageValidator as SNSMessageValidator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\Http;
use jdavidbakr\MailTracker\Contracts\MailTrackerDriver;
use jdavidbakr\MailTracker\Drivers\Ses\Jobs\SesRecordBounceJob;
use jdavidbakr\MailTracker\Drivers\Ses\Jobs\SesRecordComplaintJob;
use jdavidbakr\MailTracker\Drivers\Ses\Jobs\SesRecordDeliveryJob;

class SesDriver implements MailTrackerDriver
{
    public function callback(Request $request): Response
    {
        if (config('app.env') != 'production' && $request->message) {
            // phpunit cannot mock static methods so without making a facade
            // for SNSMessage we have to pass the json data in $request->message
            $message = new SNSMessage(json_decode($request->message, true));
        } else {
            $message = SNSMessage::fromJsonString(
                $request->getContent()
            );
            $validator = app(SNSMessageValidator::class);
            $validator->validate($message);
        }
        // If we have a topic defined, make sure this is that topic
        if (config('mail-tracker.sns-topic') && $message->offsetGet('TopicArn') != config('mail-tracker.sns-topic')) {
            return response('invalid topic ARN');
        }

        switch ($message->offsetGet('Type')) {
            case 'SubscriptionConfirmation':
                return $this->confirm_subscription($message);
            case 'Notification':
                return $this->process_notification($message);
        }

        return response('', 204);
    }

    public function resolveMessageId(SentMessage $message): ?string
    {
        /** @var \Symfony\Component\Mime\Header\Headers $headers */
        $headers = $message->getOriginalMessage()->getHeaders();

        if ($messageHeader = $headers->get('X-SES-Message-ID')) {
            return $messageHeader->getBody();
        }

        return null;
    }

    protected function confirm_subscription($message): Response
    {
        Http::get($message->offsetGet('SubscribeURL'));

        return response('subscription confirmed');
    }

    protected function process_notification($message): Response
    {
        $message = json_decode($message->offsetGet('Message'));
        switch ($message->notificationType) {
            case 'Delivery':
                $this->process_delivery($message);
                break;
            case 'Bounce':
                $this->process_bounce($message);
                break;
            case 'Complaint':
                $this->process_complaint($message);
                break;
        }

        return response('notification processed');
    }

    protected function process_delivery($message)
    {
        SesRecordDeliveryJob::dispatch($message)
            ->onQueue(config('mail-tracker.tracker-queue'));
    }

    public function process_bounce($message)
    {
        SesRecordBounceJob::dispatch($message)
            ->onQueue(config('mail-tracker.tracker-queue'));
    }

    public function process_complaint($message)
    {
        SesRecordComplaintJob::dispatch($message)
            ->onQueue(config('mail-tracker.tracker-queue'));
    }
}
