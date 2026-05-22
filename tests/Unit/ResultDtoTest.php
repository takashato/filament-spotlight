<?php

declare(strict_types=1);

use Takashato\FilamentSpotlight\Contracts\SpotlightResult;
use Takashato\FilamentSpotlight\DTOs\Handler;
use Takashato\FilamentSpotlight\DTOs\Result;

it('exposes all SpotlightResult fields via getter methods', function (): void {
    $r = new Result(
        id: 'foo',
        title: 'Foo',
        sourceKey: 'fake',
        handler: Handler::url('/foo'),
        subtitle: 'sub',
        icon: 'heroicon-o-bolt',
        badge: 'NEW',
    );

    expect($r)->toBeInstanceOf(SpotlightResult::class);
    expect($r->id())->toBe('foo');
    expect($r->title())->toBe('Foo');
    expect($r->subtitle())->toBe('sub');
    expect($r->icon())->toBe('heroicon-o-bolt');
    expect($r->badge())->toBe('NEW');
    expect($r->sourceKey())->toBe('fake');
    expect($r->handler())->toBe(['type' => 'url', 'url' => '/foo', 'target' => '_self']);
});

it('handler factories produce serializable directives', function (): void {
    expect(Handler::url('/x'))->toBe(['type' => 'url', 'url' => '/x', 'target' => '_self']);
    expect(Handler::url('/x', '_blank'))->toBe(['type' => 'url', 'url' => '/x', 'target' => '_blank']);
    expect(Handler::event('foo', ['a' => 1]))->toBe(['type' => 'event', 'name' => 'foo', 'payload' => ['a' => 1]]);
    expect(Handler::modal('app.x', ['a' => 1]))->toBe(['type' => 'modal', 'component' => 'app.x', 'props' => ['a' => 1]]);
    expect(Handler::callback('commands', 'logout'))->toBe(['type' => 'callback', 'source' => 'commands', 'id' => 'logout']);

    expect(json_encode(Handler::event('foo', ['a' => 1])))->toBeString();
});
