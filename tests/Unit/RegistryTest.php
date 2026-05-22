<?php

declare(strict_types=1);

use Takashato\FilamentSpotlight\Spotlight;
use Takashato\FilamentSpotlight\Tests\Fixtures\FakeSource;

it('sorts sources by priority desc and filters disabled ones', function (): void {
    $registry = app(Spotlight::class);
    $registry->flush();

    $registry->registerSource(new FakeSource(key: 'low', priority: 10));
    $registry->registerSource(new FakeSource(key: 'high', priority: 100));
    $registry->registerSource(new FakeSource(key: 'mid', priority: 50));
    $registry->registerSource(new FakeSource(key: 'off', priority: 200, enabled: false));

    $keys = $registry->sources()->map(fn ($s) => $s->key())->all();
    expect($keys)->toBe(['high', 'mid', 'low']);
});

it('dedupes sources by key keeping first registration', function (): void {
    $registry = app(Spotlight::class);
    $registry->flush();

    $registry->registerSource(new FakeSource(key: 'a', priority: 50));
    $registry->registerSource(new FakeSource(key: 'a', priority: 99));

    $sources = $registry->sources();
    expect($sources)->toHaveCount(1);
});

it('rejects class strings that do not implement SpotlightSource', function (): void {
    $registry = app(Spotlight::class);
    expect(fn () => $registry->registerSource(stdClass::class))
        ->toThrow(InvalidArgumentException::class);
});
