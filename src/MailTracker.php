<?php

namespace jdavidbakr\MailTracker;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use jdavidbakr\MailTracker\Model\SentEmail;
use jdavidbakr\MailTracker\Model\SentEmailUrlClicked;

class MailTracker
{
    /**
     * Set this to "false" to skip this library migrations
     */
    public static bool $runsMigrations = true;

    /**
     * The SentEmail model class name.
     */
    public static string $sentEmailModel = SentEmail::class;

    /**
     * The SentEmailUrlClicked model class name.
     */
    public static string $sentEmailUrlClickedModel = SentEmailUrlClicked::class;

    public static bool $schedulePurging = false;

    public static string $scheduleTime = '03:00';

    /**
     * This is used for resolving the mailer during a message send.
     * Read more in the docs.
     */
    public static string|null $mailer = null;

    /**
     * List of trackers which should be used.
     */
    public static array $trackers = [];

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
     * Check if it has a specific tracker.
     */
    protected static function hasTracker(string $class): bool
    {
        return in_array($class, static::$trackers);
    }

    public static function registerTrackers(Container $app): void
    {
        foreach (config('mail-tracker.trackers') as $trackerClass) {
            // check if watcher already exists
            if (self::hasTracker($trackerClass)) {
                continue;
            }

            static::$trackers[$trackerClass] = $app->make($trackerClass);
        }
    }

    public static function usedMailer(string|null $mailer): void
    {
        static::$mailer = $mailer;
    }

    /**
     * Set class name of SentEmail model.
     */
    public static function useSentEmailModel(string $sentEmailModelClass): void
    {
        static::$sentEmailModel = $sentEmailModelClass;
    }

    /**
     * Create new SentEmail model.
     */
    public static function sentEmailModel(array $attributes = []): Model|SentEmail
    {
        return new static::$sentEmailModel($attributes);
    }

    /**
     * Set class name of SentEmailUrlClicked model.
     */
    public static function useSentEmailUrlClickedModel(string $class): void
    {
        static::$sentEmailUrlClickedModel = $class;
    }

    /**
     * Create new SentEmailUrlClicked model.
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

    public static function make(): static
    {
        return app()->make(static::class);
    }

    /**
     * Legacy function
     *
     * @param [type] $url
     * @return bool
     */
    public static function hash_url($url)
    {
        // Replace "/" with "$"
        return str_replace('/', '$', base64_encode($url));
    }

    /**
     * Set email header for mailable model to attach the model the SentEmail model.
     */
    public static function attachMailableModel(Mailable $mailable, Model $model): void
    {
        $mailable->withSymfonyMessage(function ($message) use ($model) {
            $message->getHeaders()->addTextHeader('X-Mailable-Id', $model->getKey());
            $message->getHeaders()->addTextHeader('X-Mailable-Type', $model->getMorphClass());
        });
    }
}
