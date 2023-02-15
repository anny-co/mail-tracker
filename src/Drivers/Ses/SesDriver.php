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
use stdClass;

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
                return $this->confirmSubscription($message);
            case 'Notification':
                return $this->processNotification($message);
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

    protected function confirmSubscription(SNSMessage $message): Response
    {
        Http::get($message->offsetGet('SubscribeURL'));

        return response('subscription confirmed');
    }

    protected function processNotification(SNSMessage $message): Response
    {
        $message = json_decode($message->offsetGet('Message'));
        switch ($message->notificationType) {
            case 'Delivery':
                $this->processDelivery($message);
                break;
            case 'Bounce':
                $this->processBounce($message);
                break;
            case 'Complaint':
                $this->processComplaint($message);
                break;
        }

        return response('notification processed');
    }

    protected function processDelivery(stdClass $message)
    {
        dispatch(new SesRecordDeliveryJob($message))
            ->onQueue(config('mail-tracker.tracker-queue'));
    }

    public function processBounce(stdClass $message)
    {
        dispatch(new SesRecordBounceJob($message))
            ->onQueue(config('mail-tracker.tracker-queue'));
    }

    public function processComplaint(stdClass $message)
    {
        dispatch(new SesRecordComplaintJob($message))
            ->onQueue(config('mail-tracker.tracker-queue'));
    }
}
