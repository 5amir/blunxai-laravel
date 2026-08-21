<?php

namespace Blunx\AI;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Blunx\AI\Console\InitCommand;
use Blunx\AI\Console\EditCommand;
use Blunx\AI\Console\SetupCommand;
use Blunx\AI\Console\FeedbackCommand;
use Blunx\AI\Console\RunInsightsCommand;
use Blunx\AI\Services\BlunxApiClient;
use Blunx\AI\Agents\DataExecutorAgent;

/**
 * Laravel service provider for Blunx AI.
 *
 * Registers the configuration, the `blunx` middleware group, translations,
 * views, routes, Artisan commands and the service container bindings.
 */
class BlunxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/blunx.php',
            'blunx'
        );

        $this->mergeConfigFrom(__DIR__.'/../config/blunx_access.php', 'blunx_access');

        $this->app->singleton(BlunxApiClient::class);

        $this->app->bind(DataExecutorAgent::class);

        $this->app->bind(\Barryvdh\DomPDF\PDF::class, function ($app) {
            return $app->make('dompdf.wrapper');
        });
    }

    public function boot(): void
    {
        // API middleware group: replicates `web` (cookies, session, bindings)
        // without CSRF verification. Authentication is handled by the `auth`
        // middleware via the session cookie.
        Route::middlewareGroup('blunx', [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            'auth',
        ]);

        $this->loadTranslationsFrom(__DIR__.'/resources/lang', 'blunx');

        // Email views: blunx::mail.insight_mail → resources/mail/insight_mail.blade.php
        $this->loadViewsFrom(__DIR__.'/resources', 'blunx');

        $this->loadRoutesFrom(__DIR__ . '/Routes/api.php');
        $this->loadRoutesFrom(__DIR__ . '/Routes/console.php');

        $this->app->booted(function () {
            $this->app->setLocale(config('blunx.locale', 'fr'));
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                InitCommand::class,
                EditCommand::class,
                SetupCommand::class,
                FeedbackCommand::class,
                RunInsightsCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/blunx.php' => config_path('blunx.php'),
                __DIR__.'/../config/blunx_access.php' => config_path('blunx_access.php'),
            ], 'blunx-config');
        }
    }
}
