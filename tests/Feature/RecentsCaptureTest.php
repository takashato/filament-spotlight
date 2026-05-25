<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Takashato\FilamentSpotlight\Models\SpotlightRecent;
use Takashato\FilamentSpotlight\Spotlight;

function makeUser(int $id = 1, string $name = 'User'): void
{
    DB::table('users')->updateOrInsert(['id' => $id], [
        'id' => $id,
        'name' => $name,
        'email' => "user-$id@example.test",
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

beforeEach(function (): void {
    Carbon::setTestNow('2026-05-25 10:00:00');
    config()->set('spotlight.recents.enabled', true);
    config()->set('spotlight.recents.cap_per_user', 50);
    SpotlightRecent::query()->delete();
    DB::table('users')->delete();
    makeUser(1);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('records a visit as a single row', function (): void {
    app(Spotlight::class)->recordVisit(1, 'resources', 'resources:1', 'Shipment SHP-001', ['kind' => 'record']);

    expect(SpotlightRecent::query()->count())->toBe(1);
    $row = SpotlightRecent::query()->first();
    expect($row->user_id)->toBe(1);
    expect($row->source_key)->toBe('resources');
    expect($row->result_id)->toBe('resources:1');
    expect($row->title)->toBe('Shipment SHP-001');
    expect($row->payload)->toBe(['kind' => 'record']);
});

it('updates visited_at on a repeat visit (no duplicate)', function (): void {
    Carbon::setTestNow('2026-05-25 10:00:00');
    app(Spotlight::class)->recordVisit(1, 'resources', 'r1', 'Title', []);

    Carbon::setTestNow('2026-05-25 10:05:00');
    app(Spotlight::class)->recordVisit(1, 'resources', 'r1', 'Title v2', ['kind' => 'record']);

    expect(SpotlightRecent::query()->count())->toBe(1);
    $row = SpotlightRecent::query()->first();
    expect($row->title)->toBe('Title v2');
    expect($row->payload)->toBe(['kind' => 'record']);
    expect($row->visited_at->format('Y-m-d H:i:s'))->toBe('2026-05-25 10:05:00');
});

it('evicts the oldest row beyond the per-user cap', function (): void {
    config()->set('spotlight.recents.cap_per_user', 50);
    $registry = app(Spotlight::class);

    for ($i = 1; $i <= 50; $i++) {
        Carbon::setTestNow(Carbon::create(2026, 5, 25, 10, 0, $i));
        $registry->recordVisit(1, 'nav', "nav:$i", "Item $i", []);
    }

    expect(SpotlightRecent::query()->where('user_id', 1)->count())->toBe(50);

    Carbon::setTestNow('2026-05-25 11:00:00');
    $registry->recordVisit(1, 'nav', 'nav:51', 'Item 51', []);

    expect(SpotlightRecent::query()->where('user_id', 1)->count())->toBe(50);
    expect(SpotlightRecent::query()->where('result_id', 'nav:1')->exists())->toBeFalse();
    expect(SpotlightRecent::query()->where('result_id', 'nav:51')->exists())->toBeTrue();
});

it('scopes recents per user', function (): void {
    makeUser(2);

    app(Spotlight::class)->recordVisit(1, 'nav', 'a', 'A', []);
    app(Spotlight::class)->recordVisit(2, 'nav', 'a', 'A', []);

    expect(SpotlightRecent::query()->where('user_id', 1)->count())->toBe(1);
    expect(SpotlightRecent::query()->where('user_id', 2)->count())->toBe(1);
});

it('rejects invalid input without writing', function (): void {
    $registry = app(Spotlight::class);

    $registry->recordVisit(0, 'nav', 'a', 'A', []);
    $registry->recordVisit(1, '', 'a', 'A', []);
    $registry->recordVisit(1, 'nav', '', 'A', []);
    $registry->recordVisit(1, 'nav', 'a', '', []);

    expect(SpotlightRecent::query()->count())->toBe(0);
});

it('skips eviction when cap is non-positive', function (): void {
    config()->set('spotlight.recents.cap_per_user', 0);

    for ($i = 1; $i <= 3; $i++) {
        app(Spotlight::class)->recordVisit(1, 'nav', "i$i", "I $i", []);
    }

    expect(SpotlightRecent::query()->count())->toBe(3);
});
