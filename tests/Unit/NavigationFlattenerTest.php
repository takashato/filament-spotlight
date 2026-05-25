<?php

declare(strict_types=1);

use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Takashato\FilamentSpotlight\Support\NavigationFlattener;

beforeEach(function (): void {
    NavigationFlattener::forget('flattener-unit');
});

it('flattens groups + items and tags group label', function (): void {
    $a = NavigationItem::make('A')->url('/a');
    $b = NavigationItem::make('B')->url('/b');
    $group = NavigationGroup::make('Ops')->items([$a, $b]);

    $flat = NavigationFlattener::flatten([$group], 'flattener-unit');

    expect($flat)->toHaveCount(2);
    expect($flat[0]['label'])->toBe('A');
    expect($flat[0]['group'])->toBe('Ops');
    expect($flat[0]['parent'])->toBeNull();
    expect($flat[1]['label'])->toBe('B');
    expect($flat[1]['group'])->toBe('Ops');
});

it('omits items where visible() is false', function (): void {
    $visible = NavigationItem::make('Visible')->url('/v');
    $hidden = NavigationItem::make('Hidden')->url('/h')->visible(false);

    $flat = NavigationFlattener::flatten([$visible, $hidden], 'flattener-unit');

    expect($flat)->toHaveCount(1);
    expect($flat[0]['label'])->toBe('Visible');
});

it('walks child items and tags parent label', function (): void {
    $child = NavigationItem::make('Child')->url('/parent/child');
    $parent = NavigationItem::make('Parent')->url('/parent')->icon('heroicon-o-folder')->childItems([$child]);

    $flat = NavigationFlattener::flatten([$parent], 'flattener-unit');

    expect($flat)->toHaveCount(2);
    expect($flat[0]['label'])->toBe('Parent');
    expect($flat[0]['parent'])->toBeNull();
    expect($flat[1]['label'])->toBe('Child');
    expect($flat[1]['parent'])->toBe('Parent');
});

it('preserves sort order field on output rows', function (): void {
    $a = NavigationItem::make('First')->url('/a')->sort(1);
    $b = NavigationItem::make('Second')->url('/b')->sort(5);

    $flat = NavigationFlattener::flatten([$a, $b], 'flattener-unit');

    expect($flat[0]['sort'])->toBe(1);
    expect($flat[1]['sort'])->toBe(5);
});

it('returns cached result on repeated calls', function (): void {
    $calls = 0;
    $resolver = function () use (&$calls): array {
        $calls++;

        return [NavigationItem::make('Once')->url('/once')];
    };

    NavigationFlattener::flatten($resolver(), 'flattener-unit');
    NavigationFlattener::flatten($resolver(), 'flattener-unit');

    // Resolver is invoked twice (we call it manually) but flatten() should
    // short-circuit on second call and not re-walk the tree.
    expect($calls)->toBe(2);

    $flat = NavigationFlattener::flatten([], 'flattener-unit');
    expect($flat)->toHaveCount(1);
    expect($flat[0]['label'])->toBe('Once');
});

it('forget() clears the per-panel cache', function (): void {
    $first = [NavigationItem::make('Alpha')->url('/a')];
    $second = [NavigationItem::make('Beta')->url('/b')];

    NavigationFlattener::flatten($first, 'flattener-unit');
    NavigationFlattener::forget('flattener-unit');
    $flat = NavigationFlattener::flatten($second, 'flattener-unit');

    expect($flat[0]['label'])->toBe('Beta');
});

it('partitions cache by panel id', function (): void {
    $panelA = [NavigationItem::make('A')->url('/a')];
    $panelB = [NavigationItem::make('B')->url('/b')];

    NavigationFlattener::flatten($panelA, 'panel-a');
    NavigationFlattener::flatten($panelB, 'panel-b');

    expect(NavigationFlattener::flatten([], 'panel-a')[0]['label'])->toBe('A');
    expect(NavigationFlattener::flatten([], 'panel-b')[0]['label'])->toBe('B');

    NavigationFlattener::forget('panel-a');
    NavigationFlattener::forget('panel-b');
});
