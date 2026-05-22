<?php

declare(strict_types=1);

namespace Takashato\FilamentSpotlight;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Takashato\FilamentSpotlight\Contracts\SpotlightSource;

/**
 * Source registry. Singleton; resolved through the container.
 *
 * Sources may be registered as class strings (resolved lazily via the container)
 * or as concrete instances. Disabled and duplicate keys are filtered at resolve time.
 */
class Spotlight
{
    /**
     * @var array<int, class-string<SpotlightSource>|SpotlightSource>
     */
    protected array $sources = [];

    public function __construct(protected Container $container) {}

    /**
     * @param  class-string<SpotlightSource>|SpotlightSource  $source
     */
    public function registerSource(string|SpotlightSource $source): static
    {
        if (is_string($source) && ! is_subclass_of($source, SpotlightSource::class)) {
            throw new InvalidArgumentException(
                sprintf('Class [%s] must implement %s.', $source, SpotlightSource::class),
            );
        }

        $this->sources[] = $source;

        return $this;
    }

    /**
     * Resolve, filter and sort all registered sources.
     *
     * @return Collection<int, SpotlightSource>
     */
    public function sources(): Collection
    {
        $resolved = collect($this->sources)
            ->map(fn ($s): SpotlightSource => is_string($s) ? $this->container->make($s) : $s)
            ->filter(fn (SpotlightSource $s): bool => $s->isEnabled())
            ->unique(fn (SpotlightSource $s): string => $s->key())
            ->sortByDesc(fn (SpotlightSource $s): int => $s->priority())
            ->values();

        return $resolved;
    }

    public function flush(): void
    {
        $this->sources = [];
    }
}
