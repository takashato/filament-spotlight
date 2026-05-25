<?php

declare(strict_types=1);

namespace Takashato\FilamentSpotlight\Livewire;

use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Takashato\FilamentSpotlight\Contracts\SpotlightResult;
use Takashato\FilamentSpotlight\Spotlight;
use Takashato\FilamentSpotlight\SpotlightEngine;
use Throwable;

/**
 * Livewire palette component. Renders search input, grouped results, and
 * relays handler directives to the browser via `spotlight:dispatch`.
 *
 * SECURITY:
 * - URL directives validated via `assertSafeUrl()` before any browser dispatch
 * - Event payloads are recursively scalar-checked; closures + objects rejected
 * - All result fields render through Blade `{{ }}` (escaped); `MatchHighlighter`
 *   output is the only pre-escaped HTML and uses `{!! !!}` deliberately
 */
class SpotlightPalette extends Component
{
    public string $query = '';

    public bool $isOpen = false;

    public ?string $highlightedId = null;

    #[On('open-spotlight')]
    public function open(): void
    {
        $this->isOpen = true;
        $this->query = '';
        $this->highlightedId = null;
    }

    #[On('close-spotlight')]
    public function close(): void
    {
        $this->isOpen = false;
        $this->query = '';
        $this->highlightedId = null;
    }

    public function updatedQuery(): void
    {
        // Resetting highlight forces the JS layer to re-pick the first row.
        $this->highlightedId = null;
    }

    /**
     * Computed groups — engine-driven; never mutates state.
     *
     * @return Collection<string, Collection<int, SpotlightResult>>
     */
    #[Computed]
    public function groups(): Collection
    {
        $engine = app(SpotlightEngine::class);
        $trimmed = trim($this->query);

        return $trimmed === ''
            ? $engine->empty()
            : $engine->search($trimmed);
    }

    /**
     * Server-side validated handler dispatch.
     *
     * @param  array<string, mixed>  $directive
     */
    public function dispatchHandler(array $directive): void
    {
        $type = is_string($directive['type'] ?? null) ? $directive['type'] : null;

        $payload = match ($type) {
            'url' => $this->prepareUrlDirective($directive),
            'event' => $this->prepareEventDirective($directive),
            'modal' => $this->prepareModalDirective($directive),
            'callback' => $this->prepareCallbackDirective($directive),
            default => null,
        };

        if ($payload === null) {
            return;
        }

        $this->dispatch('spotlight:dispatch', directive: $payload);
    }

    /**
     * Single entry point invoked when the user activates a result.
     *
     * Records the visit (best-effort, scoped by server-trusted user id) and
     * relays the validated handler directive back to the browser via
     * `spotlight:dispatch`. Keeping both steps server-side means the Alpine
     * layer only needs to fire one wire call per activation.
     *
     * @param  array<string, mixed>  $payload
     */
    public function activate(array $payload): void
    {
        $directive = $payload['directive'] ?? null;

        $this->recordVisit([
            'sourceKey' => $payload['sourceKey'] ?? null,
            'resultId' => $payload['resultId'] ?? null,
            'title' => $payload['title'] ?? null,
            'payload' => $payload['payload'] ?? [],
        ]);

        if (is_array($directive)) {
            $this->dispatchHandler($directive);
        }
    }

    /**
     * Capture a visited result. SECURITY: scoped by `Filament::auth()->id()` —
     * never trust client-provided user identifiers.
     *
     * Invoked indirectly via `activate()` — kept public so tests and host apps
     * can drive recents capture without going through a directive.
     *
     * @param  array<string, mixed>  $event
     */
    public function recordVisit(array $event): void
    {
        if (! (bool) (config('spotlight.recents.enabled') ?? true)) {
            return;
        }

        $userId = $this->resolveAuthUserId();
        if ($userId === null) {
            return;
        }

        $sourceKey = $event['sourceKey'] ?? null;
        $resultId = $event['resultId'] ?? null;
        $title = $event['title'] ?? null;
        $payload = $event['payload'] ?? [];

        if (! is_string($sourceKey) || $sourceKey === '') {
            return;
        }
        if (! is_string($resultId) || $resultId === '') {
            return;
        }
        if (! is_string($title) || $title === '') {
            return;
        }
        if (! is_array($payload) || ! $this->isJsonSerialisable($payload)) {
            $payload = [];
        }

        try {
            app(Spotlight::class)->recordVisit($userId, $sourceKey, $resultId, $title, $payload);
        } catch (Throwable) {
            // Swallow — recents persistence is best-effort and never blocks UX.
        }
    }

    protected function resolveAuthUserId(): ?int
    {
        try {
            if (class_exists(Filament::class)) {
                $id = Filament::auth()->id();
                if (is_int($id) && $id > 0) {
                    return $id;
                }
                if (is_string($id) && ctype_digit($id)) {
                    return (int) $id;
                }
            }
        } catch (Throwable) {
            // fall through to default guard
        }

        try {
            $id = auth()->id();
            if (is_int($id) && $id > 0) {
                return $id;
            }
            if (is_string($id) && ctype_digit($id)) {
                return (int) $id;
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    public function render(): View
    {
        /** @var view-string $view */
        $view = 'spotlight::livewire.palette';

        return view($view);
    }

    /**
     * @param  array<string, mixed>  $directive
     * @return array<string, mixed>|null
     */
    protected function prepareUrlDirective(array $directive): ?array
    {
        $url = $directive['url'] ?? null;
        if (! is_string($url) || ! $this->assertSafeUrl($url)) {
            return null;
        }

        $target = ($directive['target'] ?? '_self') === '_blank' ? '_blank' : '_self';

        return ['type' => 'url', 'url' => $url, 'target' => $target];
    }

    /**
     * @param  array<string, mixed>  $directive
     * @return array<string, mixed>|null
     */
    protected function prepareEventDirective(array $directive): ?array
    {
        $name = $directive['name'] ?? null;
        $payload = $directive['payload'] ?? [];
        if (! is_string($name) || $name === '') {
            return null;
        }
        if (! is_array($payload) || ! $this->isJsonSerialisable($payload)) {
            return null;
        }

        return ['type' => 'event', 'name' => $name, 'payload' => $payload];
    }

    /**
     * @param  array<string, mixed>  $directive
     * @return array<string, mixed>|null
     */
    protected function prepareModalDirective(array $directive): ?array
    {
        $component = $directive['component'] ?? null;
        $props = $directive['props'] ?? [];
        if (! is_string($component) || $component === '') {
            return null;
        }
        if (! is_array($props) || ! $this->isJsonSerialisable($props)) {
            return null;
        }

        return ['type' => 'modal', 'component' => $component, 'props' => $props];
    }

    /**
     * @param  array<string, mixed>  $directive
     * @return array<string, mixed>|null
     */
    protected function prepareCallbackDirective(array $directive): ?array
    {
        $source = $directive['source'] ?? null;
        $id = $directive['id'] ?? null;
        if (! is_string($source) || $source === '' || ! is_string($id) || $id === '') {
            return null;
        }

        return ['type' => 'callback', 'source' => $source, 'id' => $id];
    }

    /**
     * Allow only relative URLs (`/...`, `?...`, `#...`) or same-origin https/http.
     * Reject `javascript:`, `data:`, `file:`, mailto, ftp, etc.
     */
    protected function assertSafeUrl(string $url): bool
    {
        if ($url === '') {
            return false;
        }
        if (str_starts_with($url, '/') || str_starts_with($url, '?') || str_starts_with($url, '#')) {
            // Reject protocol-relative (`//evil.com`) which would change origin.
            return ! str_starts_with($url, '//');
        }

        $parsed = parse_url($url);
        if (! is_array($parsed) || empty($parsed['host'])) {
            return false;
        }
        $scheme = strtolower((string) ($parsed['scheme'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        return is_string($appHost) && strcasecmp($parsed['host'], $appHost) === 0;
    }

    /**
     * Recursive scalar-only check; closures, resources and objects are rejected.
     */
    protected function isJsonSerialisable(mixed $value): bool
    {
        if (is_scalar($value) || $value === null) {
            return true;
        }
        if (! is_array($value)) {
            return false;
        }
        foreach ($value as $v) {
            if (! $this->isJsonSerialisable($v)) {
                return false;
            }
        }

        return true;
    }
}
