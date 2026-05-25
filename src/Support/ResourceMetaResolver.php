<?php

declare(strict_types=1);

namespace Takashato\FilamentSpotlight\Support;

use BackedEnum;
use Filament\Resources\Resource;
use Illuminate\Support\Str;
use Throwable;

/**
 * Defensive accessor for static `Resource` metadata. Filament resources may
 * throw at boot if the panel context isn't fully wired (tenancy, parent-resource,
 * etc.) — this helper swallows those failures and yields safe fallbacks so a
 * single misbehaving resource can't take down the whole search source.
 */
final class ResourceMetaResolver
{
    /**
     * @param  class-string<\Filament\Resources\Resource>  $resource
     */
    public static function label(string $resource): string
    {
        try {
            return (string) $resource::getNavigationLabel();
        } catch (Throwable) {
            return Str::headline(class_basename($resource));
        }
    }

    /**
     * @param  class-string<\Filament\Resources\Resource>  $resource
     */
    public static function url(string $resource): ?string
    {
        try {
            return $resource::getUrl();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  class-string<\Filament\Resources\Resource>  $resource
     */
    public static function icon(string $resource): ?string
    {
        try {
            $icon = $resource::getNavigationIcon();
        } catch (Throwable) {
            return null;
        }
        if (is_string($icon)) {
            return $icon;
        }
        if ($icon instanceof BackedEnum) {
            return (string) $icon->value;
        }

        return null;
    }

    /**
     * @param  class-string<\Filament\Resources\Resource>  $resource
     * @return class-string|null
     */
    public static function model(string $resource): ?string
    {
        try {
            $model = $resource::getModel();

            return $model !== '' ? $model : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  class-string<\Filament\Resources\Resource>  $resource
     */
    public static function hasNavigationLabel(string $resource): bool
    {
        try {
            return $resource::getNavigationLabel() !== '';
        } catch (Throwable) {
            return false;
        }
    }
}
