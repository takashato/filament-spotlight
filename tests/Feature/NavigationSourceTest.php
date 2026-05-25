<?php

declare(strict_types=1);

use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Takashato\FilamentSpotlight\Sources\NavigationSource;
use Takashato\FilamentSpotlight\Support\NavigationFlattener;

beforeEach(function (): void {
    NavigationFlattener::forget('test-panel');
    NavigationFlattener::forget('default');
});

function makeNavTree(): array
{
    $shipments = NavigationItem::make('Shipments')->url('/shipments')->icon('heroicon-o-truck')->sort(1);
    $invoices = NavigationItem::make('Invoices')->url('/invoices')->icon('heroicon-o-document-text')->sort(2);
    $settings = NavigationItem::make('Settings')->url('/settings')->icon('heroicon-o-cog-6-tooth')->sort(3);
    $hidden = NavigationItem::make('Secret Reports')->url('/secret')->visible(false);
    $dashboard = NavigationItem::make('Dashboard')->url('/dashboard')->sort(0);

    $ops = NavigationGroup::make('Operations')->items([$shipments, $invoices, $hidden]);

    return [$dashboard, $ops, $settings];
}

it('returns matching items sorted by score', function (): void {
    $source = new NavigationSource(
        navigationResolver: fn (): array => makeNavTree(),
        panelIdResolver: fn (): string => 'test-panel',
    );

    $results = $source->search('ship', 5);

    expect($results)->toHaveCount(1);
    expect($results->first()->title())->toContain('<mark>Ship</mark>');
    expect($results->first()->subtitle())->toBe('Operations');
});

it('hides items where visible() returns false', function (): void {
    $source = new NavigationSource(
        navigationResolver: fn (): array => makeNavTree(),
        panelIdResolver: fn (): string => 'test-panel',
    );

    $results = $source->search('secret', 5);

    expect($results)->toHaveCount(0);
});

it('returns top-level items in empty state ordered by sort', function (): void {
    $source = new NavigationSource(
        navigationResolver: fn (): array => makeNavTree(),
        panelIdResolver: fn (): string => 'test-panel',
    );

    $results = $source->empty(5);
    $titles = $results->map(fn ($r): string => $r->title())->all();

    expect($titles)->toBe(['Dashboard', 'Settings']);
});

it('uses request cache for repeated calls', function (): void {
    $calls = 0;
    $resolver = function () use (&$calls): array {
        $calls++;

        return makeNavTree();
    };

    $source = new NavigationSource(
        navigationResolver: $resolver,
        panelIdResolver: fn (): string => 'test-panel',
    );

    $source->search('ship', 5);
    $source->search('settings', 5);

    expect($calls)->toBe(1);
});

it('returns empty collection when navigation resolver throws', function (): void {
    $source = new NavigationSource(
        navigationResolver: fn (): array => throw new RuntimeException('nope'),
        panelIdResolver: fn (): string => 'test-panel',
    );

    $results = $source->search('foo', 5);
    expect($results)->toHaveCount(0);
});

it('uses url handler shape and stable id', function (): void {
    $source = new NavigationSource(
        navigationResolver: fn (): array => makeNavTree(),
        panelIdResolver: fn (): string => 'test-panel',
    );

    $result = $source->search('shipments', 1)->first();
    expect($result->handler())->toMatchArray(['type' => 'url', 'url' => '/shipments']);
    expect($result->sourceKey())->toBe('nav');
    expect($result->id())->toStartWith('nav:');
});
