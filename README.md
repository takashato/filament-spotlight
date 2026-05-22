# Filament Spotlight

macOS-Spotlight / Raycast-style command palette for Filament v5. Pluggable source contract, permission-safe, keyboard-first, WCAG 2.1 AA.

> Status: pre-1.0 — public API stable from v1.0 tag. v1.0 ships `FilamentResourceSource` + `NavigationSource`. Commands source ships in v1.1.

## Install (path repo, in-development)

```jsonc
// app/composer.json
{
  "repositories": [
    {
      "type": "path",
      "url": "../packages/filament-spotlight",
      "options": { "symlink": true }
    }
  ],
  "require": {
    "takashato/filament-spotlight": "*"
  }
}
```

```bash
git submodule update --init --recursive
composer update takashato/filament-spotlight
```

## Register

```php
use Takashato\FilamentSpotlight\SpotlightPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            SpotlightPlugin::make(),
        ]);
}
```

## Custom source (preview — full UI ships in upcoming phases)

```php
use Illuminate\Support\Collection;
use Takashato\FilamentSpotlight\Contracts\SpotlightSource;
use Takashato\FilamentSpotlight\DTOs\Handler;
use Takashato\FilamentSpotlight\DTOs\Result;

class MyCommandSource implements SpotlightSource
{
    public function key(): string { return 'commands'; }
    public function label(): string { return __('Commands'); }
    public function icon(): ?string { return 'heroicon-o-command-line'; }
    public function priority(): int { return 80; }
    public function isEnabled(): bool { return true; }

    public function search(string $query, int $limit): Collection
    {
        return collect([
            new Result(
                id: 'logout',
                title: __('Sign out'),
                sourceKey: $this->key(),
                handler: Handler::callback($this->key(), 'logout'),
            ),
        ])->filter(fn (Result $r) => str_contains(strtolower($r->title()), strtolower($query)))
          ->take($limit);
    }

    public function empty(int $limit): Collection
    {
        return collect();
    }
}
```

Register via:

```php
SpotlightPlugin::make()->withSources([MyCommandSource::class])
```

## Configuration

Publish:

```bash
php artisan vendor:publish --tag=spotlight-config
```

See `config/spotlight.php` for shortcut, limits, debounce, sources, recents, mobile breakpoint.

## Roadmap

- v1.0 — core engine, resource + navigation sources, palette UI, recents, a11y, i18n
- v1.1 — commands source (validates `callback` directive)

## License

MIT
