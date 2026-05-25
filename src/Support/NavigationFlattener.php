<?php

declare(strict_types=1);

namespace Takashato\FilamentSpotlight\Support;

use BackedEnum;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Illuminate\Container\Container as ConcreteContainer;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Flattens Filament's navigation tree into a flat list of items, suitable for
 * scoring/searching. DFS over `NavigationGroup` + `NavigationItem`. Honors
 * `visible()` chain so unauthorized items are filtered for free.
 *
 * Results are request-cached via the application container under the key
 * `spotlight.flat-nav.{panelId}` to avoid recomputation across multiple
 * source calls in the same request.
 *
 * @phpstan-type FlatNavItem array{label: string, url: ?string, icon: ?string, group: ?string, parent: ?string, sort: int}
 */
final class NavigationFlattener
{
    /**
     * Flatten and cache. Identity of `$panelId` partitions caches per panel
     * (different panels may have different navigation trees per user/session).
     *
     * @param  iterable<NavigationGroup|NavigationItem>  $navigation
     * @return array<int, array{label: string, url: ?string, icon: ?string, group: ?string, parent: ?string, sort: int}>
     */
    public static function flatten(iterable $navigation, string $panelId = 'default', ?Container $container = null): array
    {
        $cacheKey = 'spotlight.flat-nav.'.$panelId;
        $container ??= app();

        if ($container->bound($cacheKey)) {
            $cached = $container->make($cacheKey);
            if (is_array($cached)) {
                /** @var array<int, array{label: string, url: ?string, icon: ?string, group: ?string, parent: ?string, sort: int}> $cached */
                return $cached;
            }
        }

        $flat = self::walk($navigation, group: null, parent: null);
        $container->instance($cacheKey, $flat);

        return $flat;
    }

    /**
     * Clear the request cache for a panel (useful in tests).
     */
    public static function forget(string $panelId = 'default', ?ConcreteContainer $container = null): void
    {
        $container ??= app();
        $container->forgetInstance('spotlight.flat-nav.'.$panelId);
    }

    /**
     * @param  iterable<NavigationGroup|NavigationItem>  $nodes
     * @return array<int, array{label: string, url: ?string, icon: ?string, group: ?string, parent: ?string, sort: int}>
     */
    private static function walk(iterable $nodes, ?string $group, ?string $parent): array
    {
        $out = [];

        foreach ($nodes as $node) {
            if ($node instanceof NavigationGroup) {
                $label = $node->getLabel();
                $items = self::toArray($node->getItems());
                /** @var array<int, NavigationItem> $items */
                $out = array_merge($out, self::walk($items, $label, $parent));

                continue;
            }

            if (! $node instanceof NavigationItem) {
                continue;
            }

            if (! $node->isVisible()) {
                continue;
            }

            $itemLabel = $node->getLabel();

            $out[] = [
                'label' => $itemLabel,
                'url' => $node->getUrl(),
                'icon' => self::iconToString($node->getIcon()),
                'group' => $group,
                'parent' => $parent,
                'sort' => $node->getSort(),
            ];

            $children = self::toArray($node->getChildItems());
            if ($children !== []) {
                /** @var array<int, NavigationItem> $children */
                $out = array_merge($out, self::walk($children, $group, $itemLabel));
            }
        }

        return $out;
    }

    /**
     * @param  array<int, mixed>|Arrayable<int, mixed>  $items
     * @return array<int, mixed>
     */
    private static function toArray(array|Arrayable $items): array
    {
        return $items instanceof Arrayable ? $items->toArray() : $items;
    }

    private static function iconToString(string|BackedEnum|Htmlable|null $icon): ?string
    {
        if ($icon === null) {
            return null;
        }
        if (is_string($icon)) {
            return $icon;
        }
        if ($icon instanceof BackedEnum) {
            return (string) $icon->value;
        }

        return $icon->toHtml();
    }
}
