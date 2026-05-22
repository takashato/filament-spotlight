<?php

declare(strict_types=1);

namespace Takashato\FilamentSpotlight;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Takashato\FilamentSpotlight\Contracts\SpotlightSource;

class SpotlightPlugin implements Plugin
{
    /**
     * @var array<int, class-string<SpotlightSource>|SpotlightSource>
     */
    protected array $sources = [];

    protected ?int $maxResultsPerSource = null;

    protected ?int $totalResultLimit = null;

    protected ?int $debounceMs = null;

    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'spotlight';
    }

    /**
     * @param  array<int, class-string<SpotlightSource>|SpotlightSource>  $sources
     */
    public function withSources(array $sources): static
    {
        $this->sources = $sources;

        return $this;
    }

    public function maxResultsPerSource(int $limit): static
    {
        $this->maxResultsPerSource = $limit;

        return $this;
    }

    public function totalResultLimit(int $limit): static
    {
        $this->totalResultLimit = $limit;

        return $this;
    }

    public function debounceMs(int $ms): static
    {
        $this->debounceMs = $ms;

        return $this;
    }

    public function getMaxResultsPerSource(): ?int
    {
        return $this->maxResultsPerSource;
    }

    public function getTotalResultLimit(): ?int
    {
        return $this->totalResultLimit;
    }

    public function getDebounceMs(): ?int
    {
        return $this->debounceMs;
    }

    /**
     * @return array<int, class-string<SpotlightSource>|SpotlightSource>
     */
    public function getSources(): array
    {
        return $this->sources;
    }

    public function register(Panel $panel): void
    {
        // No-op in Phase 1/2. Sources, livewire components, render hooks register in Phase 3+.
    }

    public function boot(Panel $panel): void
    {
        $registry = app(Spotlight::class);

        // Plugin-declared sources take precedence as defaults; config can override.
        if ($this->sources !== []) {
            foreach ($this->sources as $source) {
                $registry->registerSource($source);
            }
        }

        // Config-declared source map: class-keyed, value `null|false` disables.
        $configured = config('spotlight.sources', []);
        if (is_array($configured)) {
            foreach ($configured as $class => $opts) {
                if ($opts === null || $opts === false) {
                    continue;
                }

                if (! is_string($class) || ! class_exists($class)) {
                    continue;
                }

                if (! is_subclass_of($class, SpotlightSource::class)) {
                    continue;
                }

                $registry->registerSource($class);
            }
        }
    }
}
