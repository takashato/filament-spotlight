<?php

declare(strict_types=1);

namespace Takashato\FilamentSpotlight\Tests;

use Filament\FilamentServiceProvider;
use Filament\Support\SupportServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Support\Facades\Schema;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Takashato\FilamentSpotlight\SpotlightServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            SupportServiceProvider::class,
            FilamentServiceProvider::class,
            SpotlightServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        // Strip the placeholder built-in source entries until Phase 3 ships the classes.
        $app['config']->set('spotlight.sources', []);

        // Provide a default eloquent user provider so `auth()->loginUsingId()` resolves.
        $app['config']->set('auth.providers.users', [
            'driver' => 'eloquent',
            'model' => TestUser::class,
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        // Minimal `users` table for the FK constraint on `spotlight_recents`.
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->nullable();
                $table->string('email')->nullable();
                $table->string('password')->nullable();
                $table->rememberToken();
                $table->timestamps();
            });
        }

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}

/**
 * @internal
 */
class TestUser extends AuthUser
{
    protected $table = 'users';

    protected $guarded = [];
}
