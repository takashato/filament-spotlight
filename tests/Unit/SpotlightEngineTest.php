<?php

declare(strict_types=1);

use Takashato\FilamentSpotlight\Spotlight;
use Takashato\FilamentSpotlight\SpotlightEngine;
use Takashato\FilamentSpotlight\Tests\Fixtures\FakeAsyncSource;
use Takashato\FilamentSpotlight\Tests\Fixtures\FakeSource;

beforeEach(function (): void {
    app(Spotlight::class)->flush();
    config()->set('spotlight.limits.per_source', 5);
    config()->set('spotlight.limits.total', 20);
});

it('returns groups keyed by source priority order', function (): void {
    $registry = app(Spotlight::class);
    $registry->registerSource(new FakeSource(key: 'low', priority: 10, items: [['id' => '1', 'title' => 'foo']]));
    $registry->registerSource(new FakeSource(key: 'high', priority: 100, items: [['id' => '2', 'title' => 'foo']]));

    $groups = app(SpotlightEngine::class)->search('foo');
    expect($groups->keys()->all())->toBe(['high', 'low']);
});

it('honors per-source limit', function (): void {
    config()->set('spotlight.limits.per_source', 2);

    $registry = app(Spotlight::class);
    $registry->registerSource(new FakeSource(key: 'a', priority: 100, items: [
        ['id' => '1', 'title' => 'foo'],
        ['id' => '2', 'title' => 'foo'],
        ['id' => '3', 'title' => 'foo'],
        ['id' => '4', 'title' => 'foo'],
    ]));

    $groups = app(SpotlightEngine::class)->search('foo');
    expect($groups->get('a'))->toHaveCount(2);
});

it('honors global total limit by trimming low-priority groups', function (): void {
    config()->set('spotlight.limits.per_source', 10);
    config()->set('spotlight.limits.total', 3);

    $items = fn (string $prefix): array => [
        ['id' => $prefix.'1', 'title' => 'foo'],
        ['id' => $prefix.'2', 'title' => 'foo'],
        ['id' => $prefix.'3', 'title' => 'foo'],
    ];

    $registry = app(Spotlight::class);
    $registry->registerSource(new FakeSource(key: 'high', priority: 100, items: $items('h')));
    $registry->registerSource(new FakeSource(key: 'low', priority: 10, items: $items('l')));

    $groups = app(SpotlightEngine::class)->search('foo');
    expect($groups->get('high'))->toHaveCount(3);
    expect($groups->get('low'))->toHaveCount(0);
});

it('dedupes by sourceKey + id across results', function (): void {
    $registry = app(Spotlight::class);
    $registry->registerSource(new FakeSource(key: 'a', priority: 100, items: [
        ['id' => '1', 'title' => 'foo'],
        ['id' => '1', 'title' => 'foo'],
        ['id' => '2', 'title' => 'foo'],
    ]));

    $groups = app(SpotlightEngine::class)->search('foo');
    expect($groups->get('a'))->toHaveCount(2);
});

it('excludes disabled sources from results', function (): void {
    $registry = app(Spotlight::class);
    $registry->registerSource(new FakeSource(key: 'on', priority: 100, items: [['id' => '1', 'title' => 'foo']]));
    $registry->registerSource(new FakeSource(key: 'off', priority: 200, enabled: false, items: [['id' => '2', 'title' => 'foo']]));

    $groups = app(SpotlightEngine::class)->search('foo');
    expect($groups->keys()->all())->toBe(['on']);
});

it('does not block other sources when one source throws', function (): void {
    $registry = app(Spotlight::class);
    $registry->registerSource(new FakeSource(key: 'broken', priority: 200, throws: new RuntimeException('upstream down')));
    $registry->registerSource(new FakeSource(key: 'ok', priority: 100, items: [['id' => '1', 'title' => 'foo']]));

    $groups = app(SpotlightEngine::class)->search('foo');
    expect($groups->get('broken'))->toHaveCount(0);
    expect($groups->get('ok'))->toHaveCount(1);
});

it('resolves async source results via Guzzle promises', function (): void {
    $registry = app(Spotlight::class);
    $registry->registerSource(new FakeAsyncSource(key: 'jira', priority: 200, items: [
        ['id' => 'SS-1', 'title' => 'foo bar'],
    ]));

    $groups = app(SpotlightEngine::class)->search('foo');
    expect($groups->get('jira'))->toHaveCount(1);
});

it('returns empty group when async source rejects', function (): void {
    $registry = app(Spotlight::class);
    $registry->registerSource(new FakeAsyncSource(key: 'jira', rejects: true));

    $groups = app(SpotlightEngine::class)->search('foo');
    expect($groups->get('jira'))->toHaveCount(0);
});

it('empty() composes empty-state results from each source', function (): void {
    $registry = app(Spotlight::class);
    $registry->registerSource(new FakeSource(key: 'a', priority: 100, items: [['id' => '1', 'title' => 'one']]));
    $registry->registerSource(new FakeSource(key: 'b', priority: 50, items: [['id' => '2', 'title' => 'two']]));

    $groups = app(SpotlightEngine::class)->empty();
    expect($groups->keys()->all())->toBe(['a', 'b']);
    expect($groups->get('a'))->toHaveCount(1);
    expect($groups->get('b'))->toHaveCount(1);
});

it('returns empty collection when registry has no sources', function (): void {
    $groups = app(SpotlightEngine::class)->search('foo');
    expect($groups)->toBeEmpty();
});
