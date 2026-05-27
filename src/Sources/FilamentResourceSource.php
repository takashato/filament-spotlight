<?php

declare(strict_types=1);

namespace Takashato\FilamentSpotlight\Sources;

use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\GlobalSearch\GlobalSearchResult;
use Filament\Resources\Resource;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use ReflectionMethod;
use Takashato\FilamentSpotlight\Contracts\RecentsAware;
use Takashato\FilamentSpotlight\Contracts\SpotlightResult;
use Takashato\FilamentSpotlight\Contracts\SpotlightSource;
use Takashato\FilamentSpotlight\DTOs\Handler;
use Takashato\FilamentSpotlight\DTOs\Result;
use Takashato\FilamentSpotlight\Support\MatchHighlighter;
use Takashato\FilamentSpotlight\Support\ResourceMetaResolver;
use Throwable;

/**
 * Built-in source: bridges every Filament resource's existing global-search
 * implementation. Permissions are honored via `Resource::canGloballySearch()`
 * which itself runs the resource's `viewAny` policy — no extra layer.
 *
 * Design note: the resources resolver is constructor-injected to keep tests
 * fast and decoupled from a fully booted panel. Defaults to `Filament::getResources()`.
 */
class FilamentResourceSource implements RecentsAware, SpotlightSource
{
    /** @var (Closure(): iterable<int, class-string<\Filament\Resources\Resource>>) */
    private Closure $resourcesResolver;

    /**
     * Per-instance reflection cache: does the resource override
     * `getGlobalSearchResultActions()`? Keyed by FQCN; class-global so safe to
     * cache across requests within an instance.
     *
     * @var array<class-string<\Filament\Resources\Resource>, bool>
     */
    private array $hasActionsCache = [];

    /**
     * @param  (callable(): iterable<int, class-string<\Filament\Resources\Resource>>)|null  $resourcesResolver
     */
    public function __construct(?callable $resourcesResolver = null)
    {
        $this->resourcesResolver = $resourcesResolver !== null
            ? Closure::fromCallable($resourcesResolver)
            : static fn (): array => Filament::getResources();
    }

    public function key(): string
    {
        return 'resources';
    }

    public function label(): string
    {
        return __('spotlight::sources.resources');
    }

    public function icon(): ?string
    {
        return 'heroicon-o-rectangle-stack';
    }

    public function priority(): int
    {
        return (int) (config('spotlight.sources.'.self::class.'.priority') ?? 100);
    }

    public function isEnabled(): bool
    {
        return (bool) (config('spotlight.sources.'.self::class.'.enabled') ?? true);
    }

    public function search(string $query, int $limit): Collection
    {
        $needle = trim($query);
        if ($needle === '') {
            return $this->empty($limit);
        }

        $resources = $this->searchableResources();
        if ($resources === []) {
            return collect();
        }

        $subLimit = max(1, (int) floor($limit / max(1, count($resources))));

        $out = collect();
        foreach ($resources as $resource) {
            try {
                /** @var Collection<int, GlobalSearchResult> $hits */
                $hits = $resource::getGlobalSearchResults($needle);
            } catch (Throwable) {
                continue;
            }

            $mapped = $hits
                ->take($subLimit)
                ->map(fn (GlobalSearchResult $gsr): ?Result => $this->mapResult($gsr, $resource, $needle))
                ->filter()
                ->values();

            $out = $out->concat($mapped);
            if ($out->count() >= $limit) {
                break;
            }
        }

        return $out->take($limit)->values();
    }

    public function empty(int $limit): Collection
    {
        $resources = $this->searchableResources();

        return collect($resources)
            ->filter(fn (string $r): bool => ResourceMetaResolver::hasNavigationLabel($r))
            ->take($limit)
            ->map(fn (string $r): SpotlightResult => $this->resourceShortcutResult($r))
            ->values();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function validateRecent(string $resultId, array $payload): ?SpotlightResult
    {
        $resource = $payload['resource'] ?? null;
        if (! is_string($resource) || ! class_exists($resource) || ! is_subclass_of($resource, Resource::class)) {
            return null;
        }

        $kind = $payload['kind'] ?? null;
        if ($kind === 'shortcut') {
            try {
                if (! $resource::canGloballySearch()) {
                    return null;
                }
            } catch (Throwable) {
                return null;
            }

            return $this->resourceShortcutResult($resource);
        }

        $record = $this->resolveRecord($payload);
        if (! $record instanceof Model) {
            return null;
        }

        try {
            $url = $resource::getUrl('view', ['record' => $record]);
        } catch (Throwable) {
            $url = null;
        }

        if (! is_string($url) || $url === '') {
            try {
                $url = $resource::getUrl('edit', ['record' => $record]);
            } catch (Throwable) {
                $url = null;
            }
        }

        if (! is_string($url) || $url === '') {
            return null;
        }

        $title = isset($payload['title']) && is_string($payload['title']) && $payload['title'] !== ''
            ? $payload['title']
            : ResourceMetaResolver::label($resource);

        $modelClass = ResourceMetaResolver::model($resource);
        $badge = $modelClass !== null ? class_basename($modelClass) : class_basename($resource);

        return new Result(
            id: $resultId,
            title: htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            sourceKey: $this->key(),
            handler: Handler::url($url),
            subtitle: null,
            icon: ResourceMetaResolver::icon($resource) ?? $this->icon(),
            badge: $badge,
            payload: $payload,
        );
    }

    /**
     * Resolve the Filament action list for a focused result row.
     *
     * Pure server-side resolution: returns fresh `Action` instances each call.
     * Never persisted, never serialized to the client. Authorization mirrors
     * `validateRecent()` — same record-rebind + view permission gate.
     *
     * Action `name` collisions across records would clash inside Filament's
     * action registry (which keys by name), so each action is renamed to
     * `spotlight::{$resultId}::{$originalName}` before return.
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, Action>
     */
    public function resolveActionsFor(string $resultId, array $payload): array
    {
        if (! (bool) (config('spotlight.sources.'.self::class.'.actions.enabled') ?? true)) {
            return [];
        }

        if (($payload['kind'] ?? null) !== 'record') {
            return [];
        }

        $resource = $payload['resource'] ?? null;
        if (! is_string($resource) || ! is_subclass_of($resource, Resource::class)) {
            return [];
        }

        $record = $this->resolveRecord($payload);
        if (! $record instanceof Model) {
            return [];
        }

        try {
            /** @var array<int, mixed> $rawActions */
            $rawActions = $resource::getGlobalSearchResultActions($record);
        } catch (Throwable) {
            return [];
        }

        $out = [];
        foreach ($rawActions as $action) {
            try {
                if (! $action instanceof Action) {
                    continue;
                }

                if (! $action->hasRecord()) {
                    $action->record($record);
                }

                if (! $action->isVisible()) {
                    continue;
                }

                $original = $action->getName();
                if (! is_string($original) || $original === '') {
                    continue;
                }

                $action->name("spotlight::{$resultId}::{$original}");
                $out[] = $action;
            } catch (Throwable) {
                // drop the offending action; never break the whole submenu.
                continue;
            }
        }

        return $out;
    }

    /**
     * Resource-class validation, route binding, and view-permission check —
     * shared between `validateRecent()` and `resolveActionsFor()`. Returns
     * `null` on any failure so callers can short-circuit cleanly.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function resolveRecord(array $payload): ?Model
    {
        $resource = $payload['resource'] ?? null;
        if (! is_string($resource) || ! class_exists($resource) || ! is_subclass_of($resource, Resource::class)) {
            return null;
        }

        $key = $payload['key'] ?? null;
        if (! is_string($key) && ! is_int($key)) {
            return null;
        }

        try {
            $record = $resource::resolveRecordRouteBinding((string) $key);
        } catch (Throwable) {
            return null;
        }

        if (! $record instanceof Model) {
            return null;
        }

        if (! $this->canViewRecord($resource, $record)) {
            return null;
        }

        return $record;
    }

    /**
     * @param  class-string<\Filament\Resources\Resource>  $resource
     */
    protected function canViewRecord(string $resource, Model $record): bool
    {
        try {
            if (method_exists($resource, 'canView')) {
                return (bool) $resource::canView($record);
            }
        } catch (Throwable) {
            return false;
        }

        try {
            return Gate::allows('view', $record);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Does the resource override `getGlobalSearchResultActions()`? Used to
     * suppress the Tab affordance on rows whose resource leaves the default
     * empty-array implementation in place. Reflection is cached per instance.
     *
     * Filament's base `Resource` pulls the method in via the `HasGlobalSearch`
     * trait. Trait methods report the using class as their declaring class, so
     * a subclass that does NOT override will resolve `getDeclaringClass()` to
     * `Resource::class`. Anything else is a deliberate override.
     *
     * @param  class-string<\Filament\Resources\Resource>  $resource
     */
    protected function resourceHasActions(string $resource): bool
    {
        if (array_key_exists($resource, $this->hasActionsCache)) {
            return $this->hasActionsCache[$resource];
        }

        if (! (bool) (config('spotlight.sources.'.self::class.'.actions.enabled') ?? true)) {
            return $this->hasActionsCache[$resource] = false;
        }

        $reflection = new ReflectionMethod($resource, 'getGlobalSearchResultActions');
        $declaringClass = $reflection->getDeclaringClass()->getName();

        return $this->hasActionsCache[$resource] = $declaringClass !== Resource::class;
    }

    protected function searchableResources(): array
    {
        try {
            $all = ($this->resourcesResolver)();
        } catch (Throwable) {
            return [];
        }

        $list = [];
        foreach ($all as $resource) {
            if (! is_string($resource) || ! is_subclass_of($resource, Resource::class)) {
                continue;
            }
            try {
                if ($resource::canGloballySearch()) {
                    $list[] = $resource;
                }
            } catch (Throwable) {
                // skip resources that throw during permission check
            }
        }

        return $list;
    }

    /**
     * @param  class-string<\Filament\Resources\Resource>  $resource
     */
    protected function mapResult(GlobalSearchResult $gsr, string $resource, string $query): ?Result
    {
        $url = $gsr->url;
        if ($url === '') {
            return null;
        }

        $titleString = $gsr->title instanceof Htmlable
            ? strip_tags($gsr->title->toHtml())
            : (string) $gsr->title;

        $detailsParts = [];
        foreach ($gsr->details as $detail) {
            if (is_scalar($detail)) {
                $detailsParts[] = (string) $detail;
            }
        }
        $subtitle = $detailsParts !== [] ? implode(' · ', $detailsParts) : null;

        $modelClass = ResourceMetaResolver::model($resource);
        $badge = $modelClass !== null ? class_basename($modelClass) : class_basename($resource);

        $recordKey = $this->extractRecordKeyFromUrl($url);
        $id = 'resources:'.sha1($resource.'|'.$titleString.'|'.$url);

        $payload = [
            'kind' => 'record',
            'resource' => $resource,
            'title' => $titleString,
        ];
        if ($recordKey !== null) {
            $payload['key'] = $recordKey;
        }
        if ($this->resourceHasActions($resource)) {
            $payload['has_actions'] = true;
        }

        return new Result(
            id: $id,
            title: MatchHighlighter::highlight($titleString, $query),
            sourceKey: $this->key(),
            handler: Handler::url($url),
            subtitle: $subtitle,
            icon: ResourceMetaResolver::icon($resource),
            badge: $badge,
            payload: $payload,
        );
    }

    /**
     * Extract a record key from a URL by taking the last numeric or slug segment
     * before any query string. Filament resource URLs are conventionally
     * `/resource-slug/{key}` or `/resource-slug/{key}/edit`. Falls back to null
     * when nothing usable is found — recents will then drop the row.
     */
    protected function extractRecordKeyFromUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return null;
        }
        $segments = array_values(array_filter(explode('/', $path), static fn (string $s): bool => $s !== ''));
        if ($segments === []) {
            return null;
        }
        $tail = end($segments);
        if (in_array($tail, ['edit', 'view', 'create'], true) && count($segments) >= 2) {
            $tail = $segments[count($segments) - 2];
        }

        return $tail !== '' ? $tail : null;
    }

    /**
     * @param  class-string<\Filament\Resources\Resource>  $resource
     */
    protected function resourceShortcutResult(string $resource): SpotlightResult
    {
        $label = ResourceMetaResolver::label($resource);
        $url = ResourceMetaResolver::url($resource);

        return new Result(
            id: 'resources:shortcut:'.sha1($resource),
            title: htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            sourceKey: $this->key(),
            handler: Handler::url($url ?? '#'),
            subtitle: null,
            icon: ResourceMetaResolver::icon($resource) ?? $this->icon(),
            badge: class_basename($resource),
            payload: [
                'kind' => 'shortcut',
                'resource' => $resource,
            ],
        );
    }
}
