<?php

declare(strict_types=1);

use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Takashato\FilamentSpotlight\Sources\FilamentResourceSource;
use Takashato\FilamentSpotlight\Sources\NavigationSource;
use Takashato\FilamentSpotlight\Support\NavigationFlattener;
use Takashato\FilamentSpotlight\Tests\Fixtures\FakeFilamentResource;

beforeEach(function (): void {
    FakeFilamentResource::reset();
    FakeFilamentResource::$rows = [
        ['title' => 'Shipment SHP-001', 'url' => '/shipments/1'],
    ];
    NavigationFlattener::forget('test-permission-panel');
});

it('FilamentResourceSource returns nothing when canGloballySearch is false', function (): void {
    FakeFilamentResource::$canSearch = false;

    $source = new FilamentResourceSource(
        resourcesResolver: fn (): array => [FakeFilamentResource::class],
    );

    expect($source->search('shipment', 5)->count())->toBe(0);
    expect($source->empty(5)->count())->toBe(0);
});

it('NavigationSource omits items hidden by visible() callback', function (): void {
    $authorizedRole = false;

    $tree = [
        NavigationItem::make('Shipments')->url('/shipments'),
        NavigationGroup::make('Admin')->items([
            NavigationItem::make('Users')->url('/users')->visible(fn (): bool => $authorizedRole),
            NavigationItem::make('Roles')->url('/roles')->visible(fn (): bool => $authorizedRole),
        ]),
    ];

    $source = new NavigationSource(
        navigationResolver: fn (): array => $tree,
        panelIdResolver: fn (): string => 'test-permission-panel',
    );

    $unauthorizedSearch = $source->search('users', 5);
    expect($unauthorizedSearch->count())->toBe(0);

    $unauthorizedEmpty = $source->empty(5);
    expect($unauthorizedEmpty->count())->toBe(1);
    expect($unauthorizedEmpty->first()->title())->toBe('Shipments');
});

it('FilamentResourceSource never bypasses canGloballySearch even when admin', function (): void {
    // Even with full state set, a false canGloballySearch flag short-circuits.
    FakeFilamentResource::$canSearch = false;
    FakeFilamentResource::$rows = [['title' => 'High Security', 'url' => '/secret']];

    $source = new FilamentResourceSource(
        resourcesResolver: fn (): array => [FakeFilamentResource::class],
    );

    expect($source->search('high', 5)->count())->toBe(0);
});
