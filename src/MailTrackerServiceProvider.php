<?php

namespace jdavidbakr\MailTracker;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use jdavidbakr\MailTracker\Events\ValidLinkEvent;
use jdavidbakr\MailTracker\Listener\DomainExistsInContentListener;
use jdavidbakr\MailTracker\Middleware\ValidateSignature;

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
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }

        // Publish pieces
        $this->publishConfig();
        $this->publishViews();

        // Register console commands
        $this->registerCommands();

        // Hook into the mailer
        Event::listen(MessageSending::class, function (MessageSending $event) {
            $tracker = new MailTracker;
            $tracker->messageSending($event);
        });
        Event::listen(MessageSent::class, function (MessageSent $mail) {
            $tracker = new MailTracker;
            $tracker->messageSent($mail);
        });

        foreach (config('mail-tracker.fallback-event-listeners', [
            DomainExistsInContentListener::class,
        ]) as $listener) {
            // This event is only fired when the ValidateSignature middleware is not able to validate the link
            Event::listen(ValidLinkEvent::class, $listener);
        }

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
            __DIR__ . '/../config/mail-tracker.php' => config_path('mail-tracker.php'),
        ], 'config');
    }

    /**
     * Publish the views
     *
     * @return void
     */
    protected function publishViews()
    {
        $this->loadViewsFrom(__DIR__ . '/views', 'emailTrakingViews');
        $this->publishes([
            __DIR__ . '/views' => base_path('resources/views/vendor/emailTrakingViews'),
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
        $config              = $this->app['config']->get('mail-tracker.route', []);
        $config['namespace'] = 'jdavidbakr\MailTracker';

        Route::group($config, function () {
            Route::get('t/{hash}', 'MailTrackerController@getT')->name('mailTracker_t');
            Route::get('n', 'MailTrackerController@getN')->name('mailTracker_n')->middleware(ValidateSignature::class);
            Route::post('sns', 'SNSController@callback')->name('mailTracker_SNS');
        });

        // Install the Admin routes
        $config_admin              = $this->app['config']->get('mail-tracker.admin-route', []);
        $config_admin['namespace'] = 'jdavidbakr\MailTracker';

        if (Arr::get($config_admin, 'enabled', true)) {
            Route::group($config_admin, function () {
                Route::get('/', 'AdminController@getIndex')->name('mailTracker_Index');
                Route::post('search', 'AdminController@postSearch')->name('mailTracker_Search');
                Route::get('clear-search', 'AdminController@clearSearch')->name('mailTracker_ClearSearch');
                Route::get('show-email/{id}', 'AdminController@getShowEmail')->name('mailTracker_ShowEmail');
                Route::get('url-detail/{id}', 'AdminController@getUrlDetail')->name('mailTracker_UrlDetail');
                Route::get('smtp-detail/{id}', 'AdminController@getSmtpDetail')->name('mailTracker_SmtpDetail');
            });
        }
    }
}
