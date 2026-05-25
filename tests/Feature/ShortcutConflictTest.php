<?php

declare(strict_types=1);

use Filament\Panel;
use Takashato\FilamentSpotlight\SpotlightPlugin;

/**
 * Verifies the Cmd+K conflict-handling contract:
 *
 *   - `override_filament=true`  → plugin clears `globalSearchKeyBindings()` on the panel
 *   - `override_filament=false` → plugin leaves the panel binding untouched
 *
 * We seed the panel with a canary binding (`mod+k`) and check whether the
 * plugin wipes it. Using a real Panel instance avoids brittle Mockery glue.
 */
function makeSeededPanel(): Panel
{
    $panel = Panel::make();
    $panel->globalSearchKeyBindings(['mod+k']);

    return $panel;
}

it('clears the panel global-search key bindings when override_filament is true', function (): void {
    config()->set('spotlight.shortcut.override_filament', true);

    $panel = makeSeededPanel();
    expect($panel->getGlobalSearchKeyBindings())->toBe(['mod+k']);

    SpotlightPlugin::make()->register($panel);

    expect($panel->getGlobalSearchKeyBindings())->toBe([]);
});

it('disables the panel global search provider when override_filament is true', function (): void {
    config()->set('spotlight.shortcut.override_filament', true);

    $panel = makeSeededPanel();

    SpotlightPlugin::make()->register($panel);

    expect($panel->getGlobalSearchProvider())->toBeNull();
});

it('leaves the panel global search provider intact when override_filament is false', function (): void {
    config()->set('spotlight.shortcut.override_filament', false);

    $panel = makeSeededPanel();

    SpotlightPlugin::make()->register($panel);

    // Default provider remains enabled (truthy).
    expect($panel->getGlobalSearchProvider())->not->toBeNull();
});

it('leaves the panel global-search key bindings intact when override_filament is false', function (): void {
    config()->set('spotlight.shortcut.override_filament', false);

    $panel = makeSeededPanel();
    expect($panel->getGlobalSearchKeyBindings())->toBe(['mod+k']);

    SpotlightPlugin::make()->register($panel);

    expect($panel->getGlobalSearchKeyBindings())->toBe(['mod+k']);
});

it('defaults to overriding when the package config is loaded as-is', function (): void {
    // The package config ships `override_filament => true`. Without the
    // host overriding it, the plugin should clear the panel binding.
    $panel = makeSeededPanel();
    SpotlightPlugin::make()->register($panel);

    expect($panel->getGlobalSearchKeyBindings())->toBe([]);
});
