<?php

namespace jdavidbakr\MailTracker\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use jdavidbakr\MailTracker\Concerns\IsSentEmailModel;
use jdavidbakr\MailTracker\Contracts\SentEmailModel;
use Symfony\Component\Mime\Header\Headers;

/**
 * @property string $hash
 * @property string $headers
 * @property string $sender
 * @property string $recipient
 * @property string $subject
 * @property string $content
 * @property int $opens
 * @property int $clicks
 * @property int|null $message_id
 * @property string|null $mailable_id
 * @property string|null $mailable_type
 * @property Collection $meta
 * @property Model|null $mailable
 */
class SentEmail extends Model implements SentEmailModel
{
    use IsSentEmailModel;

    protected $fillable = [
        'hash',
        'headers',
        'sender_name',
        'sender_email',
        'recipient_name',
        'recipient_email',
        'subject',
        'content',
        'opens',
        'clicks',
        'message_id',
        'meta',
        'opened_at',
        'clicked_at',
        'mailable_id',
        'mailable_type',
    ];

    protected $casts = [
        'meta' => 'collection',
        'opened_at' => 'datetime',
        'clicked_at' => 'datetime',
    ];


    public function fillTrackerDriver(): static
    {
        $meta = collect($this->meta);

        $driver = config('mail.driver') ?? config('mail.default');
        $meta->put('mail_driver', $driver);

        $this->meta = $meta;

        return $this;
    }

    public function fillMailableModelFromHeaders(Headers $headers): static
    {
        if ($headers->get('X-Mailable-Id') && $headers->get('X-Mailable-Type')) {
            $this->mailable_type = $this->getHeader('X-Mailable-Type');
            $this->mailable_id = $this->getHeader('X-Mailable-Id');
            $headers->remove('X-Mailable-Type');
            $headers->remove('X-Mailable-Id');
        }

        return $this;
    }

    public function mailable()
    {
        return $this->morphTo('mailable');
    }

    public function getMailDriver() : ?string {
        return $this->meta?->get('mail_driver');
    }

}
