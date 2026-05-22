<?php

declare(strict_types=1);

namespace Takashato\FilamentSpotlight\Facades;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use Takashato\FilamentSpotlight\Contracts\SpotlightSource;

/**
 * @method static \Takashato\FilamentSpotlight\Spotlight registerSource(string|SpotlightSource $source)
 * @method static Collection<int, SpotlightSource> sources()
 * @method static void flush()
 *
 * @see \Takashato\FilamentSpotlight\Spotlight
 */
class Spotlight extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Takashato\FilamentSpotlight\Spotlight::class;
    }
}
