<?php

declare(strict_types=1);

namespace Takashato\FilamentSpotlight\DTOs;

/**
 * Factory for serializable handler directives. Renderers (Livewire palette,
 * future JS clients) interpret these — sources never invoke navigation or
 * dispatching directly.
 */
final class Handler
{
    /**
     * @return array{type: 'url', url: string, target: '_self'|'_blank'}
     */
    public static function url(string $url, string $target = '_self'): array
    {
        return ['type' => 'url', 'url' => $url, 'target' => $target];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{type: 'event', name: string, payload: array<string, mixed>}
     */
    public static function event(string $name, array $payload = []): array
    {
        return ['type' => 'event', 'name' => $name, 'payload' => $payload];
    }

    /**
     * @param  array<string, mixed>  $props
     * @return array{type: 'modal', component: string, props: array<string, mixed>}
     */
    public static function modal(string $component, array $props = []): array
    {
        return ['type' => 'modal', 'component' => $component, 'props' => $props];
    }

    /**
     * @return array{type: 'callback', source: string, id: string}
     */
    public static function callback(string $sourceKey, string $id): array
    {
        return ['type' => 'callback', 'source' => $sourceKey, 'id' => $id];
    }
}
