# Authoring a Spotlight Source

A `SpotlightSource` is a class that returns search results from a single domain (resources, navigation, commands, bookmarks, an external API, etc.). The Spotlight engine merges all registered sources, ranks them, and renders them grouped by source label in the palette.

This guide walks through the contract, a complete < 30 LOC example, and the rules sources must follow.

## Contract

```php
namespace Takashato\FilamentSpotlight\Contracts;

interface SpotlightSource
{
    public function key(): string;            // stable id; used as group key + recents key
    public function label(): string;          // translated group label
    public function icon(): ?string;          // heroicon name, or null
    public function priority(): int;          // higher = shown first
    public function isEnabled(): bool;        // engine skips disabled sources
    public function search(string $query, int $limit): \Illuminate\Support\Collection;
    public function empty(int $limit): \Illuminate\Support\Collection;
}
```

Both `search()` and `empty()` MUST return a `Collection<int, SpotlightResult>`. The default DTO is `Takashato\FilamentSpotlight\DTOs\Result`.

## Full example

The package ships this fixture verbatim at `tests/Fixtures/ExampleCommandSource.php` so the docs and the test suite stay in sync:

```php
<?php

declare(strict_types=1);

namespace App\Spotlight;

use Illuminate\Support\Collection;
use Takashato\FilamentSpotlight\Contracts\SpotlightResult;
use Takashato\FilamentSpotlight\Contracts\SpotlightSource;
use Takashato\FilamentSpotlight\DTOs\Handler;
use Takashato\FilamentSpotlight\DTOs\Result;

final class ExampleCommandSource implements SpotlightSource
{
    private array $commands = [
        ['id' => 'logout', 'title' => 'Sign out'],
        ['id' => 'profile', 'title' => 'Open profile'],
    ];

    public function key(): string { return 'commands'; }
    public function label(): string { return 'Commands'; }
    public function icon(): ?string { return 'heroicon-o-command-line'; }
    public function priority(): int { return 80; }
    public function isEnabled(): bool { return true; }

    public function search(string $query, int $limit): Collection
    {
        $needle = mb_strtolower(trim($query));

        return collect($this->commands)
            ->filter(fn (array $c): bool => $needle === '' || str_contains(mb_strtolower($c['title']), $needle))
            ->take($limit)
            ->map(fn (array $c): SpotlightResult => new Result(
                id: $c['id'],
                title: $c['title'],
                sourceKey: $this->key(),
                handler: Handler::callback($this->key(), $c['id']),
            ))
            ->values();
    }

    public function empty(int $limit): Collection { return collect(); }
}
```

That fixture is exercised by the package test suite, so any breaking change to the contract surfaces as a test failure here first.

## Method walkthrough

### `key()`

Stable, lowercase ASCII identifier. Used as:

- The group key under which results are bucketed in the palette
- The `source_key` column in `spotlight_recents`
- A namespacing prefix for `Handler::callback()` directives

Pick something durable. Renaming a key invalidates every existing recents row for that source.

### `label()`

Human-readable group heading. Translate via `__()` so vi/en parity is preserved. Keep it short — it sits in the dropdown header.

### `icon()`

A heroicon name (e.g. `heroicon-o-command-line`) or `null`. Rendered next to the group label. Individual results may override per-row via the `Result::$icon` argument.

### `priority()`

Higher priority sorts first. Built-ins use 100 (resources), 90 (navigation). 80 is a reasonable starting point for custom sources. Negative values are allowed but unusual.

### `isEnabled()`

Engine guards every call. Disabled sources never have `search()` or `empty()` invoked — useful for feature-flagging behind a tenant setting.

### `search(string $query, int $limit)`

Called when the user types. The engine debounces input (default 200ms) and passes the trimmed query plus the per-source limit (default 5).

Source obligations:

- Return at most `$limit` results
- Self-enforce permissions — the engine assumes everything you return is visible to the current user
- Catch and translate exceptions yourself if you want a graceful degrade; uncaught throwables surface as a per-source error toast

### `empty(int $limit)`

Called when the query is blank. Use this to surface "default" or "popular" results: pinned items, top-level navigation, recent activity.

Returning `collect()` is fine — the source simply contributes nothing to the empty state.

## Optional: `RecentsAware`

Implement `Takashato\FilamentSpotlight\Contracts\RecentsAware` so the engine can re-validate previously-visited results against current permissions:

```php
public function validateRecent(string $resultId, array $payload): ?SpotlightResult
{
    return $this->repository->findVisible($resultId, auth()->id())
        ?->toSpotlightResult();
}
```

Return `null` for revoked or deleted records — the engine silently drops them. Full guide: [recents.md](recents.md).

## Optional: `AsyncSpotlightSource`

For sources that hit a remote API, implement `AsyncSpotlightSource` and return a `GuzzleHttp\Promise\PromiseInterface` from `searchAsync()`. The engine awaits all promises in parallel up to `limits.per_source_timeout_ms`. The synchronous `search()` method must still be implemented for fallbacks and tests.

## Registering a source

Two paths:

**Plugin builder** (recommended for app-owned sources):

```php
SpotlightPlugin::make()->withSources([
    \App\Spotlight\ExampleCommandSource::class,
])
```

**Config map** (for shared/published sources):

```php
// config/spotlight.php
'sources' => [
    \App\Spotlight\ExampleCommandSource::class => ['priority' => 80],
],
```

Set the value to `null` or `false` to disable a default. Both paths resolve through the container, so constructor injection works as expected.

## Handler types

Every result carries a serializable handler directive. The four built-ins:

- `Handler::url($url, $target)` — navigate to a relative or same-origin URL
- `Handler::event($name, $payload)` — dispatch a Livewire/JS event
- `Handler::modal($component, $props)` — open a Livewire modal component
- `Handler::callback($sourceKey, $id)` — emit `spotlight:source-callback`; v1.1 CommandSource consumes this

Full reference: [handlers.md](handlers.md).

## Result identity

The `id` you return must be stable across requests for the same logical record. Recents look up rows by `(source_key, result_id)`, so an unstable id breaks the LRU and the re-validation path.

A common pattern is to hash the natural key:

```php
id: 'cmd:'.sha1($command->slug),
```

## Anti-patterns

- Don't put closures or non-serializable objects in the handler array — directives must round-trip through `json_encode`.
- Don't bypass the per-source `$limit`. Return at most `$limit` results.
- Don't authenticate inside `search()`. Sources self-enforce policy; if a record isn't visible, exclude it before returning.
- Don't build large per-request caches without scoping by user — recents are per-user and your cache should be too.
