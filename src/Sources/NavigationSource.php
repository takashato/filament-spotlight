<?php

declare(strict_types=1);

namespace Takashato\FilamentSpotlight\Sources;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Takashato\FilamentSpotlight\Contracts\RecentsAware;
use Takashato\FilamentSpotlight\Contracts\SpotlightResult;
use Takashato\FilamentSpotlight\Contracts\SpotlightSource;
use Takashato\FilamentSpotlight\DTOs\Handler;
use Takashato\FilamentSpotlight\DTOs\Result;
use Takashato\FilamentSpotlight\Support\MatchHighlighter;
use Takashato\FilamentSpotlight\Support\NavigationFlattener;
use Throwable;

/**
 * Built-in source: matches Filament panel navigation (groups + items + clusters)
 * by label. In-memory only — no DB queries. Permissions are honored via the
 * `NavigationItem::visible()` chain handled inside `NavigationFlattener`.
 *
 * Design note: navigation and panel-id resolvers are constructor-injected to
 * keep tests fast and decoupled from a fully booted Filament panel. Defaults
 * fall back to the live `Filament` facade in production.
 */
class NavigationSource implements RecentsAware, SpotlightSource
{
    /** @var (Closure(): iterable<mixed>) */
    private Closure $navigationResolver;

    /** @var (Closure(): string) */
    private Closure $panelIdResolver;

    /**
     * @param  (callable(): iterable<mixed>)|null  $navigationResolver
     * @param  (callable(): string)|null  $panelIdResolver
     */
    public function __construct(?callable $navigationResolver = null, ?callable $panelIdResolver = null)
    {
        $this->navigationResolver = $navigationResolver !== null
            ? Closure::fromCallable($navigationResolver)
            : static fn (): array => Filament::getNavigation();

        $this->panelIdResolver = $panelIdResolver !== null
            ? Closure::fromCallable($panelIdResolver)
            : static fn (): string => Filament::getCurrentOrDefaultPanel()?->getId() ?? 'default';
    }

    public function key(): string
    {
        return 'nav';
    }

    public function label(): string
    {
        return __('spotlight::sources.navigation');
    }

    public function icon(): ?string
    {
        return 'heroicon-o-bars-3';
    }

    public function priority(): int
    {
        return (int) (config('spotlight.sources.'.self::class.'.priority') ?? 90);
    }

    public function isEnabled(): bool
    {
        return (bool) (config('spotlight.sources.'.self::class.'.enabled') ?? true);
    }

    public function search(string $query, int $limit): Collection
    {
        $items = collect($this->buildFlatNav());
        $needle = trim($query);
        if ($needle === '') {
            return $this->empty($limit);
        }

        $lowered = Str::lower($needle);

        return $items
            ->map(function (array $item) use ($lowered, $needle): array {
                $label = (string) $item['label'];
                $haystack = Str::lower($label);
                $contains = str_contains($haystack, $lowered);
                $similarity = 0.0;
                similar_text($haystack, $lowered, $similarity);
                $item['score'] = $contains ? 10 + (int) $similarity : 0;
                $item['highlighted'] = MatchHighlighter::highlight($label, $needle);

                return $item;
            })
            ->filter(fn (array $i): bool => ((int) $i['score']) > 0)
            ->sortByDesc('score')
            ->take($limit)
            ->map(fn (array $i): SpotlightResult => $this->toResult($i))
            ->values();
    }

    public function empty(int $limit): Collection
    {
        return collect($this->buildFlatNav())
            ->filter(fn (array $i): bool => $i['group'] === null && $i['parent'] === null)
            ->sortBy(fn (array $i): int => $i['sort'])
            ->take($limit)
            ->map(fn (array $i): SpotlightResult => $this->toResult($i, highlightWith: null))
            ->values();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function validateRecent(string $resultId, array $payload): ?SpotlightResult
    {
        foreach ($this->buildFlatNav() as $item) {
            if ($this->itemId($item) === $resultId) {
                return $this->toResult($item, highlightWith: null);
            }
        }

        return null;
    }

    /**
     * @return array<int, array{label: string, url: ?string, icon: ?string, group: ?string, parent: ?string, sort: int}>
     */
    protected function buildFlatNav(): array
    {
        try {
            $panelId = ($this->panelIdResolver)();
        } catch (Throwable) {
            $panelId = 'default';
        }

        $cacheKey = 'spotlight.flat-nav.'.$panelId;
        $container = app();
        if ($container->bound($cacheKey)) {
            $cached = $container->make($cacheKey);
            if (is_array($cached)) {
                /** @var array<int, array{label: string, url: ?string, icon: ?string, group: ?string, parent: ?string, sort: int}> $cached */
                return $cached;
            }
        }

        try {
            $nav = ($this->navigationResolver)();
        } catch (Throwable) {
            return [];
        }

        if (! is_iterable($nav)) {
            return [];
        }

        return NavigationFlattener::flatten($nav, $panelId, $container);
    }

    /**
     * @param  array{label: string, url: ?string, icon: ?string, group: ?string, parent: ?string, sort: int, highlighted?: string}  $item
     */
    protected function toResult(array $item, ?string $highlightWith = ''): SpotlightResult
    {
        $label = $item['label'];
        $title = $item['highlighted'] ?? MatchHighlighter::highlight($label, $highlightWith ?? '');
        $url = $item['url'] ?? '#';
        $subtitleParts = array_filter([$item['group'] ?? null, $item['parent'] ?? null]);
        $subtitle = $subtitleParts !== [] ? implode(' · ', $subtitleParts) : null;

        return new Result(
            id: $this->itemId($item),
            title: $title,
            sourceKey: $this->key(),
            handler: Handler::url($url),
            subtitle: $subtitle,
            icon: $item['icon'] ?? $this->icon(),
            payload: [
                'label' => $label,
                'url' => $item['url'] ?? null,
            ],
        );
    }

    /**
     * Stable id derived from `(label, url)`. Re-validation relies on this
     * being deterministic across requests.
     *
     * @param  array{label: string, url: ?string}  $item
     */
    protected function itemId(array $item): string
    {
        return 'nav:'.sha1($item['label'].'::'.($item['url'] ?? ''));
    }
}
