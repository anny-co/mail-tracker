<?php

namespace jdavidbakr\MailTracker\Drivers\Mailgun;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use jdavidbakr\MailTracker\Contracts\MailTrackerDriver;
use jdavidbakr\MailTracker\Drivers\Mailgun\Jobs\MailgunRecordBounceJob;
use jdavidbakr\MailTracker\Drivers\Mailgun\Jobs\MailgunRecordComplaintJob;
use jdavidbakr\MailTracker\Drivers\Mailgun\Jobs\MailgunRecordDeliveryJob;
use Symfony\Component\Mime\Header\Headers;

class MailgunDriver implements MailTrackerDriver
{

    public function __construct(
        protected string $signingKey,
        protected bool $shouldVerifySignature
    )
    {
    }

    public function resolveMessageId(SentMessage $message): ?string
    {
        /** @var Headers $headers */
        $headers = $message->getOriginalMessage()->getHeaders();

        if ($mailgunHeader = $headers->get('X-Mailgun-Message-ID')) {
            $messageId = $mailgunHeader->getBody();

            return (string)Str::of($messageId)->replace('<', '')->replace('>', '');
        }

        return null;
    }

    public function callback(Request $request): Response
    {
        $signatureData = $request->input('signature', []);

        if ($this->shouldVerifySignature) {
            if (!$this->verifyWebhookSignature(
                Arr::get($signatureData, 'timestamp'),
                Arr::get($signatureData, 'token'),
                Arr::get($signatureData, 'signature')
            )) {
                abort(419);
            }
        }

        $this->processNotification(
            $request->input('event-data', [])
        );

        return response()->noContent();
    }

    /**
     * Verifies the webhook signature with an API key.
     *
     */
    public function verifyWebhookSignature(int $timestamp, string $token, string $signature): bool
    {
        if (empty($timestamp) || empty($token) || empty($signature)) {
            return false;
        }

        $hmac = hash_hmac('sha256', $timestamp . $token, $this->signingKey);

        // hash_equals is constant time, but will not be introduced until PHP 5.6
        return hash_equals($hmac, $signature);
    }

    /**
     * @param array $eventData
     * @return void
     */
    protected function processNotification(array $eventData): void
    {
        switch (Arr::get($eventData, 'event')) {
            case 'delivered':
                $this->processDelivery($eventData);
                break;
            case 'failed':
            case 'rejected':
                $this->processBounce($eventData);
                break;
            case 'complained':
                $this->processComplaint($eventData);
                break;
        }
    }

    protected function processDelivery($eventData): void
    {
        dispatch(new MailgunRecordDeliveryJob($eventData))
            ->onQueue(config('mail-tracker.tracker-queue'));
    }

    public function processBounce($eventData): void
    {
        dispatch(new MailgunRecordBounceJob($eventData))
            ->onQueue(config('mail-tracker.tracker-queue'));
    }

    public function processComplaint($eventData): void
    {
        dispatch(new MailgunRecordComplaintJob($eventData))
            ->onQueue(config('mail-tracker.tracker-queue'));
    }

}