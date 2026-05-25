<?php

declare(strict_types=1);

use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Illuminate\Support\Facades\DB;
use Takashato\FilamentSpotlight\Models\SpotlightRecent;
use Takashato\FilamentSpotlight\Recents\RecentsContributor;
use Takashato\FilamentSpotlight\Sources\NavigationSource;
use Takashato\FilamentSpotlight\Spotlight;
use Takashato\FilamentSpotlight\SpotlightEngine;
use Takashato\FilamentSpotlight\Support\NavigationFlattener;
use Takashato\FilamentSpotlight\Tests\Fixtures\FakeSource;

beforeEach(function (): void {
    config()->set('spotlight.recents.enabled', true);
    config()->set('spotlight.recents.show_in_empty_state', 5);
    SpotlightRecent::query()->delete();
    DB::table('users')->delete();
    DB::table('users')->insert([
        'id' => 1, 'name' => 'U', 'email' => 'u@x.test',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    app(Spotlight::class)->flush();
    NavigationFlattener::forget('test-recents-panel');
    NavigationFlattener::forget('default');
});

function navTreeFor(bool $authorized): array
{
    return [
        NavigationItem::make('Shipments')->url('/shipments')->icon('heroicon-o-truck')->sort(1),
        NavigationGroup::make('Admin')->items([
            NavigationItem::make('Users')->url('/users')->visible(fn (): bool => $authorized),
        ]),
    ];
}

function navResultId(string $label, string $url): string
{
    return 'nav:'.sha1($label.'::'.$url);
}

it('drops rows whose source returns null from validateRecent', function (): void {
    // FakeSource doesn't implement RecentsAware → contributor drops it.
    app(Spotlight::class)->registerSource(new FakeSource(key: 'fake', items: [['id' => '1', 'title' => 'X']]));

    SpotlightRecent::query()->create([
        'user_id' => 1,
        'source_key' => 'fake',
        'result_id' => '1',
        'title' => 'X',
        'payload' => [],
        'visited_at' => now(),
    ]);

    $contrib = app(RecentsContributor::class);
    expect($contrib->contribute(1, 5)->all())->toBe([]);
});

it('drops navigation rows that are no longer visible', function (): void {
    $authorized = true;
    $tree = navTreeFor($authorized);

    $source = new NavigationSource(
        navigationResolver: fn () => $tree,
        panelIdResolver: fn (): string => 'test-recents-panel',
    );
    app(Spotlight::class)->registerSource($source);

    // Capture a visit while authorized.
    $usersId = navResultId('Users', '/users');
    SpotlightRecent::query()->create([
        'user_id' => 1,
        'source_key' => 'nav',
        'result_id' => $usersId,
        'title' => 'Users',
        'payload' => ['label' => 'Users', 'url' => '/users'],
        'visited_at' => now(),
    ]);

    // Now revoke role + flush nav cache, rebuild source + tree closure.
    NavigationFlattener::forget('test-recents-panel');
    $authorized = false;
    $tree = navTreeFor($authorized);

    $source2 = new NavigationSource(
        navigationResolver: fn () => $tree,
        panelIdResolver: fn (): string => 'test-recents-panel',
    );
    app(Spotlight::class)->flush();
    app(Spotlight::class)->registerSource($source2);

    $contrib = app(RecentsContributor::class);
    expect($contrib->contribute(1, 5)->all())->toBe([]);
});

it('returns navigation result when item is still visible', function (): void {
    $tree = navTreeFor(true);
    $source = new NavigationSource(
        navigationResolver: fn () => $tree,
        panelIdResolver: fn (): string => 'test-recents-panel',
    );
    app(Spotlight::class)->registerSource($source);

    $shipmentsId = navResultId('Shipments', '/shipments');
    SpotlightRecent::query()->create([
        'user_id' => 1,
        'source_key' => 'nav',
        'result_id' => $shipmentsId,
        'title' => 'Shipments',
        'payload' => ['label' => 'Shipments', 'url' => '/shipments'],
        'visited_at' => now(),
    ]);

    $results = app(RecentsContributor::class)->contribute(1, 5);
    expect($results)->toHaveCount(1);
    expect($results->first()->id())->toBe($shipmentsId);
});

it('engine empty() prepends recents group when enabled and authenticated', function (): void {
    $tree = navTreeFor(true);
    $source = new NavigationSource(
        navigationResolver: fn () => $tree,
        panelIdResolver: fn (): string => 'test-recents-panel',
    );
    app(Spotlight::class)->registerSource($source);

    SpotlightRecent::query()->create([
        'user_id' => 1,
        'source_key' => 'nav',
        'result_id' => navResultId('Shipments', '/shipments'),
        'title' => 'Shipments',
        'payload' => ['label' => 'Shipments', 'url' => '/shipments'],
        'visited_at' => now(),
    ]);

    auth()->loginUsingId(1);

    $groups = app(SpotlightEngine::class)->empty();
    expect($groups->keys()->first())->toBe('recents');
    expect($groups->get('recents'))->toHaveCount(1);
});

it('engine empty() omits recents group when disabled', function (): void {
    config()->set('spotlight.recents.enabled', false);

    $tree = navTreeFor(true);
    app(Spotlight::class)->registerSource(new NavigationSource(
        navigationResolver: fn () => $tree,
        panelIdResolver: fn (): string => 'test-recents-panel',
    ));

    SpotlightRecent::query()->create([
        'user_id' => 1,
        'source_key' => 'nav',
        'result_id' => navResultId('Shipments', '/shipments'),
        'title' => 'Shipments',
        'payload' => ['label' => 'Shipments', 'url' => '/shipments'],
        'visited_at' => now(),
    ]);

    auth()->loginUsingId(1);

    $groups = app(SpotlightEngine::class)->empty();
    expect($groups->has('recents'))->toBeFalse();
});

it('engine empty() omits recents group when no auth user', function (): void {
    $tree = navTreeFor(true);
    app(Spotlight::class)->registerSource(new NavigationSource(
        navigationResolver: fn () => $tree,
        panelIdResolver: fn (): string => 'test-recents-panel',
    ));

    auth()->logout();

    $groups = app(SpotlightEngine::class)->empty();
    expect($groups->has('recents'))->toBeFalse();
});
