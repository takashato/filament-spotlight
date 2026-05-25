<?php

declare(strict_types=1);

use Takashato\FilamentSpotlight\DTOs\Handler;

it('produces url directive with default _self target', function (): void {
    expect(Handler::url('/admin/x'))->toBe([
        'type' => 'url',
        'url' => '/admin/x',
        'target' => '_self',
    ]);
});

it('produces url directive with explicit _blank target', function (): void {
    expect(Handler::url('/external', '_blank'))->toBe([
        'type' => 'url',
        'url' => '/external',
        'target' => '_blank',
    ]);
});

it('produces event directive with empty payload by default', function (): void {
    expect(Handler::event('foo:happened'))->toBe([
        'type' => 'event',
        'name' => 'foo:happened',
        'payload' => [],
    ]);
});

it('produces event directive with payload preserved', function (): void {
    $payload = ['shipmentId' => 42, 'status' => 'delivered'];

    expect(Handler::event('shipment:updated', $payload))->toBe([
        'type' => 'event',
        'name' => 'shipment:updated',
        'payload' => $payload,
    ]);
});

it('produces modal directive with component + props', function (): void {
    expect(Handler::modal('app.profile-modal', ['userId' => 7]))->toBe([
        'type' => 'modal',
        'component' => 'app.profile-modal',
        'props' => ['userId' => 7],
    ]);
});

it('produces modal directive with empty props by default', function (): void {
    expect(Handler::modal('app.bare'))->toBe([
        'type' => 'modal',
        'component' => 'app.bare',
        'props' => [],
    ]);
});

it('produces callback directive with sourceKey + id', function (): void {
    expect(Handler::callback('commands', 'logout'))->toBe([
        'type' => 'callback',
        'source' => 'commands',
        'id' => 'logout',
    ]);
});

it('all directives are JSON-serializable round-trip', function (): void {
    $cases = [
        Handler::url('/x'),
        Handler::url('/y', '_blank'),
        Handler::event('e', ['a' => 1, 'b' => 'two']),
        Handler::modal('app.x', ['n' => 5]),
        Handler::callback('src', 'id'),
    ];

    foreach ($cases as $directive) {
        $encoded = json_encode($directive);
        expect($encoded)->toBeString();
        expect(json_decode((string) $encoded, true))->toBe($directive);
    }
});
