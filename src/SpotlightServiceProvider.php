<?php

declare(strict_types=1);

namespace Takashato\FilamentSpotlight;

use Illuminate\Support\ServiceProvider;

class SpotlightServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/spotlight.php',
            'spotlight',
        );

        $this->app->singleton(Spotlight::class, fn ($app) => new Spotlight($app));

        $this->app->singleton(
            SpotlightEngine::class,
            fn ($app) => new SpotlightEngine($app, $app->make(Spotlight::class)),
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/spotlight.php' => config_path('spotlight.php'),
            ], 'spotlight-config');
        }
    }
}
