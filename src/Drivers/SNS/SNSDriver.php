<?php

namespace jdavidbakr\MailTracker\Drivers\SNS;

use App\Http\Requests;
use Aws\Sns\Message as SNSMessage;
use Aws\Sns\MessageValidator as SNSMessageValidator;
use Event;
use GuzzleHttp\Client as Guzzle;
use Illuminate\Mail\SentMessage;
use Illuminate\Routing\Controller;
use jdavidbakr\MailTracker\MailTrackerDriverController;
use jdavidbakr\MailTracker\RecordBounceJob;
use jdavidbakr\MailTracker\RecordComplaintJob;
use jdavidbakr\MailTracker\RecordDeliveryJob;
use function jdavidbakr\MailTracker\app;
use function jdavidbakr\MailTracker\config;

class SNSDriver extends MailTrackerDriverController
{
    public function callback(Request $request)
    {
        if (config('app.env') != 'production' && $request->message) {
            // phpunit cannot mock static methods so without making a facade
            // for SNSMessage we have to pass the json data in $request->message
            $message = new SNSMessage(json_decode($request->message, true));
        } else {
            $message = SNSMessage::fromRawPostData();
            $validator = app(SNSMessageValidator::class);
            $validator->validate($message);
        }
        // If we have a topic defined, make sure this is that topic
        if (config('mail-tracker.sns-topic') && $message->offsetGet('TopicArn') != config('mail-tracker.sns-topic')) {
            return 'invalid topic ARN';
        }

        switch ($message->offsetGet('Type')) {
            case 'SubscriptionConfirmation':
                return $this->confirm_subscription($message);
            case 'Notification':
                return $this->process_notification($message);
        }
    }

    protected function confirm_subscription($message)
    {
        $client = new Guzzle();
        $client->get($message->offsetGet('SubscribeURL'));
        return 'subscription confirmed';
    }

    protected function process_notification($message)
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
        return 'notification processed';
    }

    protected function process_delivery($message)
    {
        RecordDeliveryJob::dispatch($message)
            ->onQueue(config('mail-tracker.tracker-queue'));
    }

    public function process_bounce($message)
    {
        RecordBounceJob::dispatch($message)
            ->onQueue(config('mail-tracker.tracker-queue'));
    }

    public function process_complaint($message)
    {
        RecordComplaintJob::dispatch($message)
            ->onQueue(config('mail-tracker.tracker-queue'));
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


}
