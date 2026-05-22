<?php

declare(strict_types=1);

use Takashato\FilamentSpotlight\SpotlightPlugin;
use Takashato\FilamentSpotlight\Tests\Fixtures\FakeSource;

it('exposes a stable plugin id', function (): void {
    expect(SpotlightPlugin::make()->getId())->toBe('spotlight');
});

it('captures fluent setter values', function (): void {
    $plugin = SpotlightPlugin::make()
        ->maxResultsPerSource(10)
        ->totalResultLimit(50)
        ->debounceMs(150)
        ->withSources([FakeSource::class]);

    expect($plugin->getMaxResultsPerSource())->toBe(10);
    expect($plugin->getTotalResultLimit())->toBe(50);
    expect($plugin->getDebounceMs())->toBe(150);
    expect($plugin->getSources())->toHaveCount(1);
});
