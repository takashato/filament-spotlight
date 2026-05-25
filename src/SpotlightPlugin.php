<?php

declare(strict_types=1);

namespace Takashato\FilamentSpotlight;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Facades\FilamentAsset;
use Takashato\FilamentSpotlight\Concerns\RegistersRenderHooks;
use Takashato\FilamentSpotlight\Contracts\SpotlightSource;
use Takashato\FilamentSpotlight\Support\KeyBindingParser;

class SpotlightPlugin implements Plugin
{
    use RegistersRenderHooks;

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
        // Render hooks register here (panel-specific). Source + asset registration
        // happens in boot() so that config + Livewire are fully booted first.
        $this->registerRenderHooks();

        if ($this->shouldOverrideFilamentShortcut()) {
            // Suppress Filament's built-in global search entirely so Spotlight
            // owns both the Cmd+K binding AND the topbar trigger pill.
            // `globalSearch(false)` removes the pill; `globalSearchKeyBindings([])`
            // belt-and-braces the keyboard binding for older Filament versions.
            $panel->globalSearch(false);
            $panel->globalSearchKeyBindings([]);
        }
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

        // Livewire component + JS asset registration both live in
        // SpotlightServiceProvider::boot() so they resolve outside panel context
        // (Livewire's /livewire/update endpoint never boots the panel).

        // Resolve the active key binding. When `override_filament=false`, prefer the
        // configured fallback so Spotlight + Filament's Cmd+K can coexist.
        $rawShortcut = $this->shouldOverrideFilamentShortcut()
            ? (string) config('spotlight.shortcut.keys', 'mod+k')
            : (string) config('spotlight.shortcut.fallback', 'mod+shift+k');

        FilamentAsset::registerScriptData([
            'spotlightConfig' => [
                'shortcut' => [
                    'keys' => $rawShortcut,
                    'binding' => KeyBindingParser::parse($rawShortcut),
                ],
                'debounceMs' => (int) config('spotlight.debounce_ms', 200),
                'announcements' => [
                    'opened' => __('spotlight::accessibility.palette_opened'),
                    'closed' => __('spotlight::accessibility.palette_closed'),
                    'summary' => __('spotlight::accessibility.results_summary'),
                    'singular' => __('spotlight::accessibility.results_summary_singular'),
                    'empty' => __('spotlight::accessibility.results_empty'),
                    'loading' => __('spotlight::accessibility.loading'),
                ],
            ],
        ]);
    }

    protected function shouldOverrideFilamentShortcut(): bool
    {
        return (bool) config('spotlight.shortcut.override_filament', true);
    }
}
