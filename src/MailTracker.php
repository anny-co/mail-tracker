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
     *
     * @var bool
     */
    public static bool $runsMigrations = true;

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

    /**
     * List of trackers which should be used.
     *
     * @var array
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
     * @param string $class
     * @return bool
     */
    protected static function hasTracker(string $class): bool
    {
        return in_array($class, static::$trackers);
    }

    /**
     * @param Container $app
     * @return void
     */
    public static function registerTrackers(Container $app): void
    {
        foreach (config('mail-tracker.trackers') as $trackerClass) {

            // check if watcher already exists
            if(self::hasTracker($trackerClass)) {
                continue;
            }

            static::$trackers[$trackerClass] = $app->make($trackerClass);
        }
    }

    /**
     * @param string|null $mailer
     * @return void
     */
    public static function usedMailer(string|null $mailer): void
    {
        static::$mailer = $mailer;
    }

    /**
     * Set class name of SentEmail model.
     *
     * @param string $sentEmailModelClass
     * @return void
     */
    public static function useSentEmailModel(string $sentEmailModelClass): void
    {
        static::$sentEmailModel = $sentEmailModelClass;
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

    /**
     * @param string $time
     * @return void
     */
    public static function shouldSchedulePurging(string $time = '03:00'): void
    {
        static::$schedulePurging = true;
        static::$scheduleTime = $time;
    }

    /**
     * @return static
     */
    public static function make(): static {
        return app()->make(static::class);
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
}
