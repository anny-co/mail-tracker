<?php

namespace jdavidbakr\MailTracker\Concerns;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use jdavidbakr\MailTracker\Contracts\SentEmailModel;
use jdavidbakr\MailTracker\MailTracker;
use Symfony\Component\Mime\Header\Headers;

trait IsSentEmailModel
{
    public static function bootIsSentEmailModel()
    {
        static::deleting(function (Model|SentEmailModel $email) {
            if ($filePath = $email->meta?->get('content_file_path')) {
                Storage::disk(config('mail-tracker.tracker-filesystem'))->delete($filePath);
            }
        });
    }

    public function getConnectionName()
    {
        $connName = config('mail-tracker.connection');

        return $connName ?: config('database.default');
    }

    public function mailable()
    {
        return $this->morphTo('mailable');
    }

    public function urlClicks()
    {
        return $this->hasMany(MailTracker::$sentEmailUrlClickedModel);
    }

    public function mailer(): Attribute
    {
        return Attribute::make(
            get: function () {
                return $this->meta?->get('mailer') ?? $this->meta?->get('mail_driver');
            },
            set: function ($value) {
                $meta = collect($this->meta);

                $meta->put('mail_driver', $value);

//                $this->meta = $meta;

                return [
                    'meta' => $meta,
                ];
            }
        );
    }

    /**
     * Returns a collection of all headers requested from our stored header info
     *
     * @return \Illuminate\Support\Collection
     */
    public function getAllHeaders()
    {
        return collect(preg_split("/(\r\n)(?!\s)/", $this->headers))
            ->filter(function ($header) {
                return preg_match('/:/', $header);
            })
            ->transform(function ($header) {
                $header = Str::replace("\r\n", '', $header);
                [$key, $value] = explode(':', $header, 2);

                return collect([
                    'key' => trim($key),
                    'value' => trim($value),
                ]);
            })->filter(function ($header) {
                return $header->get('key');
            })->keyBy('key')
            ->transform(function ($header) {
                return $header->get('value');
            });
    }

    /**
     * Returns the header requested from our stored header info
     */
    public function getHeader($key)
    {
        return $this->getAllHeaders()->get($key);
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

    public function fillContent(string $originalHtml, string $hash): static
    {
        $logContent = config('mail-tracker.log-content', true);

        if (! $logContent) {
            return $this;
        }

        $logContentStrategy = config('mail-tracker.log-content-strategy', 'database');

        if (! in_array($logContentStrategy, ['database', 'filesystem'])) {
            return $this;
        }

        $databaseContent = null;

        // handling filesystem strategy
        if ($logContentStrategy === 'filesystem') {
            // store body in html file
            $basePath = config('mail-tracker.tracker-filesystem-folder', 'mail-tracker');
            $fileSystem = config('mail-tracker.tracker-filesystem');
            $contentFilePath = "{$basePath}/{$hash}.html";

            try {
                Storage::disk($fileSystem)->put($contentFilePath, $originalHtml);
            } catch (\Exception $e) {
                Log::warning($e->getMessage());
                // fail silently
            }

            $meta = collect($this->meta);
            $meta->put('content_file_path', $contentFilePath);
            $this->meta = $meta;
        }

        // handling database strategy
        if ($logContentStrategy === 'database') {
            $databaseContent = Str::length($originalHtml) > config('mail-tracker.content-max-size', 65535)
                ? Str::substr($originalHtml, 0, config('mail-tracker.content-max-size', 65535)).'...'
                : $originalHtml;
        }

        $this->content = $databaseContent;

        return $this;
    }

    /**
     * Returns a bootstrap class about the success/failure of the message
     *
     * @return [type] [description]
     */
    public function getReportClassAttribute()
    {
        if (! empty($this->meta) && $this->meta->has('success')) {
            if ($this->meta->get('success')) {
                return 'success';
            } else {
                return 'danger';
            }
        } else {
            return '';
        }
    }

    public function getSenderAttribute()
    {
        return $this->sender_name.' <'.$this->sender_email.'>';
    }

    public function getRecipientAttribute()
    {
        return $this->recipient_name.' <'.$this->recipient_email.'>';
    }

    /**
     * Returns the smtp detail for this message ()
     *
     * @return [type] [description]
     */
    public function getSmtpInfoAttribute()
    {
        if (empty($this->meta)) {
            return '';
        }
        $meta = $this->meta;
        $responses = [];
        if ($meta->has('smtpResponse')) {
            $response = $meta->get('smtpResponse');
            $delivered_at = $meta->get('delivered_at');
            $responses[] = $response.' - Delivered '.$delivered_at;
        }
        if ($meta->has('failures') && ! empty($meta->get('mailgun_message_bounce')['delivery-status'])) {
            $deliveryStatus = $meta->get('mailgun_message_bounce')['delivery-status'];
            foreach ($meta->get('failures') as $failure) {
                if (! empty($deliveryStatus['code'])) {
                    $responses[] = $deliveryStatus['code'].' ('.$deliveryStatus['message'].'): '.$deliveryStatus['description'].' ('.$failure['emailAddress'].')';
                } else {
                    $responses[] = 'Generic Failure ('.$failure['emailAddress'].')';
                }
            }
        } elseif ($meta->has('failures')) {
            foreach ($meta->get('failures') as $failure) {
                if (! empty($failure['status'])) {
                    $responses[] = $failure['status'].' ('.$failure['action'].'): '.$failure['diagnosticCode'].' ('.$failure['emailAddress'].')';
                } else {
                    $responses[] = 'Generic Failure ('.$failure['emailAddress'].')';
                }
            }
        } elseif ($meta->has('complaint')) {
            $complaint_time = $meta->get('complaint_time');
            if ($meta->get('complaint_type')) {
                $responses[] = 'Complaint: '.$meta->get('complaint_type').' at '.$complaint_time;
            } else {
                $responses[] = 'Complaint at '.$complaint_time->format('n/d/y g:i a');
            }
        }

        return implode(' | ', $responses);
    }

    /**
     * Get content according to log-content-strategy.
     */
    public function getContentAttribute(): ?string
    {
        if ($content = $this->attributes['content']) {
            return $content;
        }
        if ($contentFilePath = $this->meta?->get('content_file_path')) {
            try {
                return Storage::disk(config('mail-tracker.tracker-filesystem'))->get($contentFilePath);
            } catch (FileNotFoundException $e) {
                return null;
            }
        }

        return null;
    }
}
