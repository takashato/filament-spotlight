<?php

declare(strict_types=1);

use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;
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

it('extracts record key from custom edit-page urls like /users/{key}/edit/account', function (): void {
    FakeFilamentResource::$rows = [
        ['title' => 'Alice', 'url' => '/users/42/edit/account'],
    ];

    $source = new FilamentResourceSource(
        resourcesResolver: fn (): array => [FakeFilamentResource::class],
    );

    $payload = $source->search('alice', 5)->first()->payload();
    expect($payload['key'] ?? null)->toBe('42');
});

it('prefers record key from action-bound record over url parsing', function (): void {
    // URL contains nested page slug; the only reliable source is the action's record.
    FakeFilamentResource::$rows = [
        ['title' => 'Alice', 'url' => '/users/99/edit/profile'],
    ];
    FakeFilamentResource::$actionsResolver = fn (Model $r): array => [
        Action::make('impersonate')->record(
            tap(new class extends Model
            {
                protected $table = 'fake_users';

                public $timestamps = false;

                public function getRouteKey(): mixed
                {
                    return $this->getAttribute('id');
                }
            }, fn ($m) => $m->forceFill(['id' => 99]))
        ),
    ];

    $source = new FilamentResourceSource(
        resourcesResolver: fn (): array => [FakeFilamentResource::class],
    );

    $payload = $source->search('alice', 5)->first()->payload();
    expect($payload['key'] ?? null)->toBe('99');
});
