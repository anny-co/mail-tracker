<?php

namespace jdavidbakr\MailTracker;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use jdavidbakr\MailTracker\Console\Commands\PurgeSentEmailsCommand;
use jdavidbakr\MailTracker\Contracts\MailerResolver;
use jdavidbakr\MailTracker\Http\Controllers\AdminController;
use jdavidbakr\MailTracker\Http\Controllers\CallbackController;
use jdavidbakr\MailTracker\Http\Controllers\MailTrackerController;
use jdavidbakr\MailTracker\Jobs\PurgeSentEmailsJob;

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

        // Schedule commands
        $this->scheduleCommands();

        // Hook into the mailer
        Event::listen(MessageSending::class, function(MessageSending $event) {
            $this->app->make(MailTracker::class)->messageSending($event);
        });
        Event::listen(MessageSent::class, function(MessageSent $mail) {
            $this->app->make(MailTracker::class)->messageSent($mail);
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
        $this->app->scoped(MailerResolver::class, MailTrackerMailerResolver::class);
        $this->app->scoped(MailTrackerManager::class, function($app){
            return new MailTrackerManager($app);
        });
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
                PurgeSentEmailsCommand::class,
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
            Route::post('callback/{driver}', CallbackController::class)->name('mailTracker_callback');

            // we keep the sns callback here for backwards compatibility, other drivers will load under callback/
            // this prevents conflicts where a driver _could_ be called 'n'
            Route::post('sns', CallbackController::class)
                ->name('mailTracker_SNS')
                ->defaults('driver', 'ses');
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

    protected function scheduleCommands()
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            if(MailTracker::$schedulePurging){
                $schedule->job(
                    new PurgeSentEmailsJob(),
                    config('mail-tracker.tracker-queue')
                )->dailyAt(MailTracker::$scheduleTime);
            }
        });
    }
}
