<?php

declare(strict_types=1);

namespace Takashato\FilamentSpotlight;

use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Takashato\FilamentSpotlight\Livewire\SpotlightPalette;

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
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'spotlight');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'spotlight');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Register the JS asset globally so `php artisan filament:assets` discovers
        // it even when no panel boots. CSS is bundled into the host's Tailwind
        // theme via `@import` + `@source` (see Vite docs in package README).
        FilamentAsset::register([
            Js::make('spotlight-palette', __DIR__.'/../resources/js/spotlight.js')->module(),
        ], 'takashato/filament-spotlight');

        // Register the Livewire component globally so the update endpoint
        // (POST /livewire/update) can resolve it without a panel boot.
        Livewire::component('spotlight-palette', SpotlightPalette::class);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/spotlight.php' => config_path('spotlight.php'),
            ], 'spotlight-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/spotlight'),
            ], 'spotlight-views');

            $this->publishes([
                __DIR__.'/../resources/lang' => $this->app->langPath('vendor/spotlight'),
            ], 'spotlight-translations');

            $this->publishes([
                __DIR__.'/../resources/js' => public_path('vendor/spotlight/js'),
                __DIR__.'/../resources/css' => public_path('vendor/spotlight/css'),
            ], 'spotlight-assets');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'spotlight-migrations');
        }
    }
}
