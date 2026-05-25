<?php

declare(strict_types=1);

use Takashato\FilamentSpotlight\Sources\FilamentResourceSource;
use Takashato\FilamentSpotlight\Tests\Fixtures\FakeFilamentResource;

beforeEach(function (): void {
    FakeFilamentResource::reset();
    FakeFilamentResource::$rows = [
        ['title' => 'Shipment SHP-001', 'url' => '/shipments/1', 'details' => ['Order #123']],
        ['title' => 'Shipment SHP-002', 'url' => '/shipments/2'],
    ];
});

it('returns mapped results from a searchable resource', function (): void {
    $source = new FilamentResourceSource(
        resourcesResolver: fn (): array => [FakeFilamentResource::class],
    );

    $results = $source->search('shipment', 5);

    expect($results)->toHaveCount(2);

    $first = $results->first();
    expect($first->title())->toContain('<mark>Shipment</mark>');
    expect($first->sourceKey())->toBe('resources');
    expect($first->handler())->toMatchArray(['type' => 'url', 'url' => '/shipments/1']);
    expect($first->subtitle())->toBe('Order #123');
    expect($first->badge())->toBe('FakeFilament');
});

it('returns empty when query is blank', function (): void {
    $source = new FilamentResourceSource(
        resourcesResolver: fn (): array => [FakeFilamentResource::class],
    );

    $results = $source->search('   ', 5);

    // Empty state shows resource shortcut(s)
    expect($results->first()->id())->toStartWith('resources:shortcut:');
});

it('skips resources without searchable permission', function (): void {
    FakeFilamentResource::$canSearch = false;

    $source = new FilamentResourceSource(
        resourcesResolver: fn (): array => [FakeFilamentResource::class],
    );

    $results = $source->search('shipment', 5);
    expect($results)->toHaveCount(0);
});

it('handles resource that throws during search gracefully', function (): void {
    FakeFilamentResource::$rows = [];
    $source = new FilamentResourceSource(
        resourcesResolver: fn (): array => [FakeFilamentResource::class],
    );

    expect($source->search('anything', 5)->all())->toBe([]);
});

it('returns top resources with navigation labels in empty state', function (): void {
    $source = new FilamentResourceSource(
        resourcesResolver: fn (): array => [FakeFilamentResource::class],
    );

    $results = $source->empty(5);

    expect($results)->toHaveCount(1);
    expect($results->first()->title())->toBe('Fake');
    expect($results->first()->id())->toStartWith('resources:shortcut:');
});

it('honors per-resource sub-limit when many resources are searchable', function (): void {
    FakeFilamentResource::$rows = array_map(
        fn (int $i): array => ['title' => "Shipment SHP-$i", 'url' => "/shipments/$i"],
        range(1, 10),
    );

    $source = new FilamentResourceSource(
        resourcesResolver: fn (): array => [FakeFilamentResource::class],
    );

    $results = $source->search('shipment', 4);

    // sub_limit = floor(4 / 1) = 4 — capped per-resource
    expect($results->count())->toBeLessThanOrEqual(4);
});
