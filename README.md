# Filament Spotlight

Spotlight-style command palette for Filament v5.

<!-- TODO: badges row (CI status, latest version, downloads) -->
<!-- ![Tests](https://github.com/takashato/filament-spotlight/actions/workflows/tests.yml/badge.svg) -->
<!-- ![Static Analysis](https://github.com/takashato/filament-spotlight/actions/workflows/static.yml/badge.svg) -->

A keyboard-first global search palette for Filament admin panels. Pluggable sources, permission-safe, accessible by design.

## Features

- Cmd/Ctrl+K opens a modal palette over any Filament panel page
- Pluggable `SpotlightSource` contract — wire your own data in under 30 lines
- Built-in `FilamentResourceSource` brings every resource's global search into the palette
- Built-in `NavigationSource` searches the panel navigation tree (groups, items, child items)
- Per-user recents with LRU eviction and re-validation against current permissions
- WCAG 2.1 AA combobox pattern, screen-reader announcements, full keyboard navigation
- Dark mode + RTL out of the box; English and Vietnamese translations shipped

## Demo

<!-- TODO: GIF -->

## Requirements

- PHP `^8.4`
- Laravel `^12.0` or `^13.0`
- Filament `^5.3`

## Installation

```bash
composer require takashato/filament-spotlight
```

Register the plugin in your panel provider:

```php
use Takashato\FilamentSpotlight\SpotlightPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugin(SpotlightPlugin::make());
}
```

Run the migration to create the `spotlight_recents` table (per-user recently visited results):

```bash
php artisan migrate
```

## Configuration

Publish the config to `config/spotlight.php`:

```bash
php artisan vendor:publish --tag=spotlight-config
```

| Key | Default | Purpose |
| --- | --- | --- |
| `shortcut.keys` | `mod+k` | Primary palette shortcut. `mod` = Cmd on macOS, Ctrl elsewhere. |
| `shortcut.override_filament` | `true` | When true, the plugin clears Filament's built-in Cmd+K so the palette owns the binding. |
| `shortcut.fallback` | `mod+shift+k` | Used when `override_filament` is false so the palette and Filament search can coexist. |
| `limits.per_source` | `5` | Max results returned per source per query. |
| `limits.total` | `20` | Hard cap on results across all sources after merging. |
| `limits.per_source_timeout_ms` | `500` | Soft timeout per source; slow sources are skipped. |
| `debounce_ms` | `200` | Client-side input debounce. |
| `sources` | resource + nav | Class-keyed map. Set value to `null` or `false` to disable a built-in. |
| `recents.enabled` | `true` | Master toggle for the per-user recents row. |
| `recents.cap_per_user` | `50` | LRU cap; older rows evicted on visit. |
| `recents.show_in_empty_state` | `5` | Recents shown when the query is blank. |
| `mobile_breakpoint` | `md` | Tailwind breakpoint at which the palette switches to bottom-sheet layout. |

## Built-in Sources

- `FilamentResourceSource` — bridges the existing `Resource::canGloballySearch()` + `getGloballySearchableAttributes()` of every registered Filament resource. No per-resource code changes required.
- `NavigationSource` — searches the panel navigation tree (groups, items, clusters, child items). Honors `visible()`.

Disable a built-in by setting its config entry to `null`:

```php
// config/spotlight.php
'sources' => [
    \Takashato\FilamentSpotlight\Sources\FilamentResourceSource::class => ['priority' => 100],
    \Takashato\FilamentSpotlight\Sources\NavigationSource::class => null,
],
```

## Custom Sources

Implement the `SpotlightSource` contract and register it via the plugin:

```php
SpotlightPlugin::make()->withSources([\App\Spotlight\TaskCommandSource::class]);
```

Full walkthrough with a < 30 LOC example: [docs/sources.md](docs/sources.md).

## Handler Directives

Sources return serializable handler directives — never closures. Four types are supported: `url`, `event`, `modal`, `callback`. Reference: [docs/handlers.md](docs/handlers.md).

## Recents

Recents are captured per-authenticated-user when the palette dispatches `spotlight:result-visited`. Sources may opt into re-validation by implementing `RecentsAware` so revoked-access rows never leak. See [docs/recents.md](docs/recents.md).

## Accessibility

The palette implements the WAI-ARIA combobox pattern (1.2 listbox popup), with managed focus, aria-live result announcements, and full keyboard navigation (arrow keys, Home, End, Enter, Escape). Targets WCAG 2.1 AA.

## Internationalization

Vietnamese (`vi`) and English (`en`) ship in `resources/lang/`. The test suite includes a parity check that fails on any drift between language files.

## Testing

```bash
composer test           # Pest, parallel
composer lint           # PHPStan level 5
composer format:check   # Pint
```

## Contributing

<!-- TODO: contributing guide -->

Issues and pull requests welcome. When opening a bug report, please scrub any production identifiers (record IDs, user emails) from screenshots and stack traces.

## License

MIT. See [LICENSE](LICENSE).
