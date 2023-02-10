<?php

namespace jdavidbakr\MailTracker;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use jdavidbakr\MailTracker\Http\Controllers\AdminController;
use jdavidbakr\MailTracker\Http\Controllers\MailTrackerController;

class MailTrackerServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        if (MailTracker::$runsMigrations && $this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        // Publish pieces
        $this->publishConfig();
        $this->publishViews();

        // Register console commands
        $this->registerCommands();

        // Hook into the mailer
        Event::listen(MessageSending::class, function(MessageSending $event) {
            $tracker = new MailTracker;
            $tracker->messageSending($event);
        });
        Event::listen(MessageSent::class, function(MessageSent $mail) {
            $tracker = new MailTracker;
            $tracker->messageSent($mail);
        });

        // Install the routes
        $this->installRoutes();
    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Publish the configuration files
     *
     * @return void
     */
    protected function publishConfig()
    {
        $this->publishes([
            __DIR__.'/../config/mail-tracker.php' => config_path('mail-tracker.php')
        ], 'config');
    }

    /**
     * Publish the views
     *
     * @return void
     */
    protected function publishViews()
    {
        $this->loadViewsFrom(__DIR__.'/views', 'emailTrakingViews');
        $this->publishes([
            __DIR__.'/views' => base_path('resources/views/vendor/emailTrakingViews'),
            ]);
    }

    public function registerCommands()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\MigrateRecipients::class,
            ]);
        }
    }

    /**
     * Install the needed routes
     *
     * @return void
     */
    protected function installRoutes()
    {
        Route::group(config('mail-tracker.route', []), function () {
            Route::get('t/{hash}', [MailTrackerController::class, 'getT'])->name('mailTracker_t');
            Route::get('l/{url}/{hash}', [MailTrackerController::class, 'getL'])->name('mailTracker_l');
            Route::get('n', [MailTrackerController::class, 'getN'])->name('mailTracker_n');
            Route::post('sns', [SNSController::class, 'callback'])->name('mailTracker_SNS');
        });

        // Install the Admin routes
        $adminRouteConfig = config('mail-tracker.admin-route', []);
        if (Arr::get($adminRouteConfig, 'enabled', true)) {
            Route::group($adminRouteConfig, function () {
                Route::get('/', [AdminController::class, 'getIndex'])->name('mailTracker_Index');
                Route::post('search', [AdminController::class, 'postSearch'])->name('mailTracker_Search');
                Route::get('clear-search', [AdminController::class, 'clearSearch'])->name('mailTracker_ClearSearch');
                Route::get('show-email/{id}', [AdminController::class, 'getShowEmail'])->name('mailTracker_ShowEmail');
                Route::get('url-detail/{id}', [AdminController::class, 'getUrlDetail'])->name('mailTracker_UrlDetail');
                Route::get('smtp-detail/{id}', [AdminController::class, 'getSmtpDetail'])->name('mailTracker_SmtpDetail');
            });
        }
    }
}
