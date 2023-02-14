<?php

namespace jdavidbakr\MailTracker;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use jdavidbakr\MailTracker\Contracts\SentEmailModel;
use jdavidbakr\MailTracker\Contracts\Tracker;
use jdavidbakr\MailTracker\Contracts\TrackerCreator;
use jdavidbakr\MailTracker\Events\EmailSentEvent;
use jdavidbakr\MailTracker\Model\SentEmail;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\Multipart\AlternativePart;
use Symfony\Component\Mime\Part\Multipart\MixedPart;
use Symfony\Component\Mime\Part\Multipart\RelatedPart;
use Symfony\Component\Mime\Part\TextPart;

class MailTrackerCreator implements TrackerCreator
{

    public function create(Email $message, string $mailer)
    {
        $headers = $message->getHeaders();
        $hash = $this->getUniqueHash();
        $subject = $message->getSubject();
        $headers->addTextHeader('X-Mailer-Hash', $hash);

        // check for the do not track
        if ($headers->get('X-No-Track')) {
            // Don't send with this header
            $headers->remove('X-No-Track');
            // Don't track this email
            return;
        }

        foreach ($message->getTo() as $toAddress) {
            foreach ($message->getFrom() as $fromAddress) {
                // handle single recipient and sender
                $contentHtml = $this->addTrackers($message, $hash);

                // Create sent email model
                /** @var SentEmail $sentEmail */
                $sentEmail = tap(MailTracker::sentEmailModel([
                    'hash' => $hash,
                    'headers' => $headers->toString(),
                    'sender_name' => $fromAddress->getName(),
                    'sender_email' => $fromAddress->getAddress(),
                    'recipient_name' => $toAddress->getName(),
                    'recipient_email' => $toAddress->getAddress(),
                    'subject' => $subject,
                    'opens' => 0,
                    'clicks' => 0,
                    'message_id' => Str::uuid(),
                ]), function (Model|SentEmailModel $sentEmail) use ($contentHtml, $hash, $headers, $mailer) {
                    $sentEmail
                        ->setAttribute('mailer', $mailer)
                        ->fillContent($contentHtml, $hash)
                        ->fillMailableModelFromHeaders($headers)
                        ->save();
                });

                Event::dispatch(new EmailSentEvent($sentEmail));
            }
        }
    }

    public function addTrackers(Email $message, string $hash): string
    {
        $originalContent = $message->getBody();
        $originalHtml = '';

        if (
            ($originalContent instanceof (AlternativePart::class)) ||
            ($originalContent instanceof (MixedPart::class)) ||
            ($originalContent instanceof (RelatedPart::class))
        ) {
            $messageBody = $message->getBody() ?: [];
            $newParts = [];
            foreach ($messageBody->getParts() as $part) {
                if ($part->getMediaSubtype() == 'html') {
                    $originalHtml = $part->getBody();
                    $newParts[] = new TextPart(
                        $this->handleTrackers($originalHtml, $hash),
                        $message->getHtmlCharset(),
                        $part->getMediaSubtype(),
                        null
                    );
                } else if ($part->getMediaSubtype() == 'alternative') {
                    if (method_exists($part, 'getParts')) {
                        foreach ($part->getParts() as $p) {
                            if ($p->getMediaSubtype() == 'html') {
                                $originalHtml = $p->getBody();
                                $newParts[] = new TextPart(
                                    $this->handleTrackers($originalHtml, $hash),
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
            $message->setBody(new (get_class($originalContent))(...$newParts));
        } else {
            $originalHtml = $originalContent->getBody();
            if ($originalContent->getMediaSubtype() == 'html') {
                $message->setBody(new TextPart(
                        $this->handleTrackers($originalHtml, $hash),
                        $message->getHtmlCharset(),
                        $originalContent->getMediaSubtype(),
                        null
                    )
                );
            }
        }

        return $originalHtml;
    }

    public function handleTrackers(string $originalHtml, string $hash): string
    {
        /** @var Tracker $tracker */
        foreach (MailTracker::$trackers as $trackerClass => $tracker) {
            $originalHtml = $tracker->convert($originalHtml, $hash);
        }

        return $originalHtml;
    }

    protected function getUniqueHash(): string
    {
        do {
            $hash = app(Str::class)->random(32);
        } while (MailTracker::sentEmailModel()->newQuery()->where('hash', $hash)->exists());

        return $hash;
    }
}