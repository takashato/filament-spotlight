<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Takashato\FilamentSpotlight\DTOs\Handler;
use Takashato\FilamentSpotlight\Livewire\SpotlightPalette;
use Takashato\FilamentSpotlight\Spotlight;
use Takashato\FilamentSpotlight\Tests\Fixtures\FakeSource;

/**
 * Phase 4 covers SpotlightPalette state + handler validation. Full Blade
 * rendering (icons, error bags, Filament view stack) is verified by the
 * host smoke test in Phase 7 — testbench bootstrap is intentionally minimal.
 */
function makePalette(): SpotlightPalette
{
    return new SpotlightPalette;
}

beforeEach(function (): void {
    $registry = app(Spotlight::class);
    $registry->flush();
    $registry->registerSource(new FakeSource(
        key: 'fake',
        label: 'Fake',
        priority: 100,
        items: [
            ['id' => '1', 'title' => 'Apple shipment'],
            ['id' => '2', 'title' => 'Banana shipment'],
        ],
    ));

    config()->set('app.url', 'https://example.test');
});

it('starts closed', function (): void {
    expect(makePalette()->isOpen)->toBeFalse();
});

it('opens via the open() listener and resets query', function (): void {
    $palette = makePalette();
    $palette->query = 'apple';
    $palette->open();

    expect($palette->isOpen)->toBeTrue()
        ->and($palette->query)->toBe('');
});

it('returns engine search results when query is set', function (): void {
    $palette = makePalette();
    $palette->query = 'apple';

    $groups = $palette->groups();

    expect($groups)->toBeInstanceOf(Collection::class)
        ->and($groups->has('fake'))->toBeTrue();
    expect($groups->get('fake')->pluck('title')->all())->toContain('Apple shipment');
});

it('returns empty-state composition when query is blank', function (): void {
    $groups = makePalette()->groups();

    expect($groups->get('fake')->count())->toBeGreaterThan(0);
});

it('rejects javascript: urls', function (): void {
    $palette = makePalette();
    $accepted = invade($palette)->prepareUrlDirective(['type' => 'url', 'url' => 'javascript:alert(1)']);
    expect($accepted)->toBeNull();
});

it('rejects external https hosts', function (): void {
    $palette = makePalette();
    $accepted = invade($palette)->prepareUrlDirective(['type' => 'url', 'url' => 'https://evil.com/exfil']);
    expect($accepted)->toBeNull();
});

it('rejects protocol-relative urls', function (): void {
    $palette = makePalette();
    $accepted = invade($palette)->prepareUrlDirective(['type' => 'url', 'url' => '//evil.com/exfil']);
    expect($accepted)->toBeNull();
});

it('rejects data: and file: schemes', function (): void {
    $palette = makePalette();
    expect(invade($palette)->prepareUrlDirective(['url' => 'data:text/html,<x>']))->toBeNull();
    expect(invade($palette)->prepareUrlDirective(['url' => 'file:///etc/passwd']))->toBeNull();
});

it('accepts relative urls', function (): void {
    $palette = makePalette();
    $accepted = invade($palette)->prepareUrlDirective(Handler::url('/admin/shipments/1'));
    expect($accepted)->toBe(['type' => 'url', 'url' => '/admin/shipments/1', 'target' => '_self']);
});

it('accepts query-only and hash-only urls', function (): void {
    $palette = makePalette();
    expect(invade($palette)->prepareUrlDirective(['url' => '?page=2'])['url'] ?? null)->toBe('?page=2');
    expect(invade($palette)->prepareUrlDirective(['url' => '#top'])['url'] ?? null)->toBe('#top');
});

it('accepts same-origin https urls', function (): void {
    $palette = makePalette();
    $accepted = invade($palette)->prepareUrlDirective(Handler::url('https://example.test/admin'));
    expect($accepted['url'] ?? null)->toBe('https://example.test/admin');
});

it('rejects event payloads containing non-scalar values', function (): void {
    $palette = makePalette();
    $accepted = invade($palette)->prepareEventDirective([
        'name' => 'something',
        'payload' => ['blob' => fopen('php://memory', 'r')],
    ]);
    expect($accepted)->toBeNull();
});

it('accepts event directives with scalar payloads', function (): void {
    $palette = makePalette();
    $accepted = invade($palette)->prepareEventDirective(Handler::event('foo', ['k' => 'v', 'n' => 1]));
    expect($accepted['name'] ?? null)->toBe('foo');
});

it('accepts callback directives', function (): void {
    $palette = makePalette();
    $accepted = invade($palette)->prepareCallbackDirective(Handler::callback('commands', 'logout'));
    expect($accepted)->toBe(['type' => 'callback', 'source' => 'commands', 'id' => 'logout']);
});

it('rejects callback directives missing source or id', function (): void {
    $palette = makePalette();
    expect(invade($palette)->prepareCallbackDirective(['source' => '', 'id' => 'x']))->toBeNull();
    expect(invade($palette)->prepareCallbackDirective(['source' => 'x', 'id' => '']))->toBeNull();
});

it('accepts modal directives with valid props', function (): void {
    $palette = makePalette();
    $accepted = invade($palette)->prepareModalDirective(Handler::modal('app.foo', ['k' => 'v']));
    expect($accepted['component'] ?? null)->toBe('app.foo');
});

it('activate() relays a valid url directive without throwing', function (): void {
    $palette = makePalette();

    // Smoke test — activate() composes recordVisit() + dispatchHandler().
    // recordVisit no-ops when no auth user is present (testbench default).
    $palette->activate([
        'sourceKey' => 'fake',
        'resultId' => 'fake:1',
        'title' => 'Apple shipment',
        'payload' => ['kind' => 'record'],
        'directive' => Handler::url('/admin/shipments/1'),
    ]);

    expect(true)->toBeTrue();
});

it('activate() ignores a missing or non-array directive', function (): void {
    $palette = makePalette();

    $palette->activate([
        'sourceKey' => 'fake',
        'resultId' => 'fake:1',
        'title' => 'Apple shipment',
    ]);

    expect(true)->toBeTrue();
});
