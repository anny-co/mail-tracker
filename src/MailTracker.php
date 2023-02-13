<?php

namespace jdavidbakr\MailTracker;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use jdavidbakr\MailTracker\Contracts\MailerResolver;
use jdavidbakr\MailTracker\Contracts\MailTrackerDriver;
use jdavidbakr\MailTracker\Contracts\SentEmailModel;
use jdavidbakr\MailTracker\Events\EmailSentEvent;
use jdavidbakr\MailTracker\Model\SentEmail;
use jdavidbakr\MailTracker\Model\SentEmailUrlClicked;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\Multipart\AlternativePart;
use Symfony\Component\Mime\Part\Multipart\MixedPart;
use Symfony\Component\Mime\Part\Multipart\RelatedPart;
use Symfony\Component\Mime\Part\TextPart;

class MailTracker
{
    // Set this to "false" to skip this library migrations
    public static $runsMigrations = true;

    /**
     * The SentEmail model class name.
     *
     * @var string
     */
    public static string $sentEmailModel = SentEmail::class;

    /**
     * The SentEmailUrlClicked model class name.
     *
     * @var string
     */
    public static string $sentEmailUrlClickedModel = SentEmailUrlClicked::class;

    /**
     * @var bool
     */
    public static bool $schedulePurging = false;

    /**
     * @var string
     */
    public static string $scheduleTime = '03:00';

    /**
     * This is used for resolving the mailer during a message send.
     * Read more in the docs.
     *
     * @var string|null
     */
    public static string|null $mailer = null;

    protected $hash;

    /**
     * Configure this library to not register its migrations.
     *
     * @return static
     */
    public static function ignoreMigrations()
    {
        static::$runsMigrations = false;

        return static::make();
    }

    /**
     * Set class name of SentEmail model.
     *
     * @param string $sentEmailModelClass
     * @return void
     */
    public static function useSentEmailModel(string $sentEmailModelClass): void {
        static::$sentEmailModel = $sentEmailModelClass;
    }

    public static function usedMailer(string|null $mailer)
    {
        static::$mailer = $mailer;
    }

    /**
     * Create new SentEmail model.
     *
     * @param array $attributes
     * @return Model|SentEmail
     */
    public static function sentEmailModel(array $attributes = []): Model|SentEmail
    {
        return new static::$sentEmailModel($attributes);
    }

    /**
     * Set class name of SentEmailUrlClicked model.
     *
     * @param string $class
     * @return void
     */
    public static function useSentEmailUrlClickedModel(string $class): void
    {
        static::$sentEmailUrlClickedModel = $class;
    }

    /**
     * Create new SentEmailUrlClicked model.
     *
     * @param array $attributes
     * @return Model|SentEmailUrlClicked
     */
    public static function sentEmailUrlClickedModel(array $attributes = []): Model|SentEmailUrlClicked
    {
        return new static::$sentEmailUrlClickedModel($attributes);
    }

    public static function shouldSchedulePurging(string $time = '03:00'): void
    {
        static::$schedulePurging = true;
        static::$scheduleTime = $time;
    }

    public function __construct(protected MailTrackerManager $manager, protected MailerResolver $resolver)
    {
    }

    public static function make(): static {
        return app()->make(static::class);
    }

    /**
     * Inject the tracking code into the message
     */
    public function messageSending(MessageSending $event)
    {
        $message = $event->message;


        // Create the trackers
        $mailer = $this->resolver->resolve($event->data);
        $this->createTrackers($message, $mailer);
    }

    public function messageSent(MessageSent $event): void
    {
        $sentMessage = $event->sent;
        $headers = $sentMessage->getOriginalMessage()->getHeaders();
        $hash = optional($headers->get('X-Mailer-Hash'))->getBody();
        $sentEmail = MailTracker::sentEmailModel()->newQuery()->where('hash', $hash)->first();

        if ($sentEmail) {
            // Identify the driver the message was sent with
            /** @var MailTrackerDriver $driver */
            $driver = $this->manager->driver($sentEmail->mailer);

            $messageId = $driver->resolveMessageId($sentMessage);
            if($messageId === null) {
                $messageId = $sentMessage->getMessageId();
            }

            $sentEmail->message_id = $messageId;
            $sentEmail->save();
        }
    }

    protected function addTrackers($html, $hash)
    {
        if (config('mail-tracker.inject-pixel')) {
            $html = $this->injectTrackingPixel($html, $hash);
        }
        if (config('mail-tracker.track-links')) {
            $html = $this->injectLinkTracker($html, $hash);
        }

        return $html;
    }

    protected function injectTrackingPixel($html, $hash)
    {
        // Append the tracking url
        $tracking_pixel = '<img border=0 width=1 alt="" height=1 src="'.route('mailTracker_t', [$hash]).'" />';

        $linebreak = app(Str::class)->random(32);
        $html = str_replace("\n", $linebreak, $html);

        if (preg_match("/^(.*<body[^>]*>)(.*)$/", $html, $matches)) {
            $html = $matches[1].$matches[2].$tracking_pixel;
        } else {
            $html = $html . $tracking_pixel;
        }
        $html = str_replace($linebreak, "\n", $html);

        return $html;
    }

    protected function injectLinkTracker($html, $hash)
    {
        $this->hash = $hash;

        $html = preg_replace_callback(
            "/(<a[^>]*href=[\"])([^\"]*)/",
            [$this, 'inject_link_callback'],
            $html
        );

        return $html;
    }

    protected function inject_link_callback($matches)
    {
        if (empty($matches[2])) {
            $url = app()->make('url')->to('/');
        } else {
            $url = str_replace('&amp;', '&', $matches[2]);
        }

        return $matches[1].route(
            'mailTracker_n',
            [
                'l' => $url,
                'h' => $this->hash
            ]
        );
    }

    /**
     * Legacy function
     *
     * @param [type] $url
     * @return boolean
     */
    public static function hash_url($url)
    {
        // Replace "/" with "$"
        return str_replace("/", "$", base64_encode($url));
    }

    /**
     * Set email header for mailable model to attach the model the SentEmail model.
     *
     * @param Mailable $mailable
     * @param Model $model
     * @return void
     */
    public static function attachMailableModel(Mailable $mailable, Model $model): void
    {
        $mailable->withSymfonyMessage(function($message) use ($model) {
            $message->getHeaders()->addTextHeader('X-Mailable-Id', $model->getKey());
            $message->getHeaders()->addTextHeader('X-Mailable-Type', $model->getMorphClass());
        });
    }

    /**
     * Create the trackers
     *
     * @param  Email $message
     * @return void
     */
    protected function createTrackers(Email $message, string $mailer): void
    {
        foreach ($message->getTo() as $toAddress) {
            $to_email = $toAddress->getAddress();
            $to_name = $toAddress->getName();
            foreach ($message->getFrom() as $fromAddress) {
                $from_email = $fromAddress->getAddress();
                $from_name = $fromAddress->getName();
                $headers = $message->getHeaders();
                if ($headers->get('X-No-Track')) {
                    // Don't send with this header
                    $headers->remove('X-No-Track');
                    // Don't track this email
                    continue;
                }
                do {
                    $hash = app(Str::class)->random(32);
                    $used = MailTracker::sentEmailModel()->newQuery()->where('hash', $hash)->count();
                } while ($used > 0);
                $headers->addTextHeader('X-Mailer-Hash', $hash);
                $subject = $message->getSubject();

                $original_content = $message->getBody();
                $original_html = '';
                if(
                    ($original_content instanceof(AlternativePart::class)) ||
                    ($original_content instanceof(MixedPart::class)) ||
                    ($original_content instanceof(RelatedPart::class))
                ) {
                    $messageBody = $message->getBody() ?: [];
                    $newParts = [];
                    foreach($messageBody->getParts() as $part) {
                        if($part->getMediaSubtype() == 'html') {
                            $original_html = $part->getBody();
                            $newParts[] = new TextPart(
                                $this->addTrackers($original_html, $hash),
                                $message->getHtmlCharset(),
                                $part->getMediaSubtype(),
                                null
                            );
                        } else if ($part->getMediaSubtype() == 'alternative') {
                            if (method_exists($part, 'getParts')) {
                                foreach ($part->getParts() as $p) {
                                    if($p->getMediaSubtype() == 'html') {
                                        $original_html = $p->getBody();
                                        $newParts[] = new TextPart(
                                            $this->addTrackers($original_html, $hash),
                                            $message->getHtmlCharset(),
                                            $p->getMediaSubtype(),
                                            null
                                        );

                                        break;
                                    }
                                }
                            }
                        } else {
                            $newParts[] = $part;
                        }
                    }
                    $message->setBody(new (get_class($original_content))(...$newParts));
                } else {
                    $original_html = $original_content->getBody();
                    if($original_content->getMediaSubtype() == 'html') {
                        $message->setBody(new TextPart(
                                $this->addTrackers($original_html, $hash),
                                $message->getHtmlCharset(),
                                $original_content->getMediaSubtype(),
                                null
                            )
                        );
                    }
                }

                /** @var SentEmail $tracker */
                $tracker = tap(MailTracker::sentEmailModel([
                    'hash' => $hash,
                    'headers' => $headers->toString(),
                    'sender_name' => $from_name,
                    'sender_email' => $from_email,
                    'recipient_name' => $to_name,
                    'recipient_email' => $to_email,
                    'subject' => $subject,
                    'opens' => 0,
                    'clicks' => 0,
                    'message_id' => Str::uuid(),
                ]), function(Model|SentEmailModel $sentEmail) use ($original_html, $hash, $headers, $mailer) {
                    $sentEmail
                        ->setAttribute('mailer', $mailer)
                        ->fillContent($original_html, $hash)
                        ->fillMailableModelFromHeaders($headers)
                        ->save();
                });

                Event::dispatch(new EmailSentEvent($tracker));
            }
        }
    }
}
