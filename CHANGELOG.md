# Changelog

All notable changes to `takashato/filament-spotlight` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.0] - 2026-05-26

### Added

- `FilamentResourceSource` now surfaces `Resource::getGlobalSearchResultActions()` inside a Tab-activated submenu on the palette. Full Filament action lifecycle works on the palette host: modals, confirmations, forms, and authorization gating fire as they would on a list page.
- `SpotlightPalette` is now a first-class Filament action host (`HasActions`, `HasSchemas` traits) — `<x-filament-actions::modals />` mounts the action modal stack outside the palette overlay.
- Result rows whose resource overrides `getGlobalSearchResultActions()` advertise a `Tab` hint and `aria-haspopup="menu"`. Rows without overrides keep their existing behavior.
- Config flag `spotlight.sources.FilamentResourceSource.actions.enabled` (default `true`) — ops kill-switch for incident response.
- En + vi translations for `spotlight.actions.{label,tab_hint,loading,empty,error,announce}`.

### Notes

- Resource shortcut rows (empty-state) and recents do not surface actions — the action surface is record-bound only.
- Custom sources cannot attach Filament actions yet — would require a `Result` DTO change.

## [1.0.0] - 2026-05-25

First stable release.

### Added

- Pluggable `SpotlightSource` contract with five lifecycle methods (`key`, `label`, `icon`, `priority`, `isEnabled`, `search`, `empty`).
- `SpotlightResult` contract + `Result` DTO with `id`, `title`, `sourceKey`, `handler`, and optional `subtitle`, `icon`, `badge`, `payload`.
- `Handler` factory for the four serializable directive types: `url`, `event`, `modal`, `callback`.
- `Spotlight` registry with class-string and instance registration paths, dedupe by source key, priority sort, and disabled-source filtering.
- `SpotlightEngine` with parallel source execution, per-source timeout, total-result cap, and recents merge for the empty state.
- `SpotlightPlugin` Filament v5 plugin with `withSources()`, `maxResultsPerSource()`, `totalResultLimit()`, `debounceMs()` builder methods. Integrates via `$panel->plugin(SpotlightPlugin::make())`.
- Built-in `FilamentResourceSource` bridging existing `Resource::canGloballySearch()` + `getGloballySearchableAttributes()`. No per-resource code required.
- Built-in `NavigationSource` searching the Filament panel navigation tree. Honors `visible()` and DFSes child items.
- `NavigationFlattener` with per-panel request-cache and visibility filter.
- `SpotlightPalette` Livewire component implementing the WAI-ARIA combobox pattern.
- Trigger pill (Cmd+K hint) rendered via render hook.
- Per-user recents persisted in `spotlight_recents` (server-trusted `user_id`, source key, result id, title, payload, `visited_at`). LRU eviction at `recents.cap_per_user`.
- Optional `RecentsAware` contract + `HandlesRecents` trait so sources can re-validate captured rows against current permissions on read.
- `KeyBindingParser` for cross-platform shortcut declarations (`mod+k` resolves to Cmd on macOS, Ctrl elsewhere).
- `MatchHighlighter` wrapping query matches in `<mark>` for visual feedback.
- WCAG 2.1 AA combobox pattern: managed focus, aria-live result announcements, full keyboard navigation (arrows, Home, End, Enter, Escape).
- Dark mode and RTL parity across all rendered components.
- Mobile responsive layout switching to a bottom-sheet at the configured Tailwind breakpoint.
- Vietnamese (`vi`) and English (`en`) translations across `accessibility`, `recents`, `sources`, `spotlight` namespaces.
- Async source contract `AsyncSpotlightSource` returning Guzzle promises for parallel external fetches.
- Configuration file with shortcut, limits, debounce, source map, recents, mobile-breakpoint settings; publishable via `php artisan vendor:publish --tag=spotlight-config`.

### Security

- Handler `url` directives are restricted to relative or same-origin URLs. `javascript:`, `data:`, `file:`, protocol-relative, and external https hosts are rejected at validation time.
- Handler `event` payloads are scalar-only. Objects, closures, and resources are rejected before dispatch.
- Recents rows are scoped to the server-trusted `Filament::auth()->id()`. Client-supplied user ids are ignored.
- Recents rows are re-validated on read against `RecentsAware::validateRecent()` so revoked-access records cannot leak.
- Source registration validates that class strings implement `SpotlightSource` before binding.
- The `Spotlight::recordVisit()` API rejects empty source key, empty result id, empty title, or non-positive user id without writing.

### Tested

- Pest 4 unit + feature suites covering: contracts and DTOs, registry, engine, key binding parser, navigation flattener, match highlighter, both built-in sources, accessibility (combobox pattern + announcements), permission enforcement (resource + navigation), recents capture (upsert, LRU, scope, validation), recents permission re-validation, shortcut conflict resolution, palette mount + handler dispatch + URL allowlist, i18n parity (en vs vi key sets).
- PHPStan level 5 clean (Larastan).
- Pint clean (Laravel preset, `declare_strict_types` enforced).

[Unreleased]: https://github.com/takashato/filament-spotlight/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/takashato/filament-spotlight/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/takashato/filament-spotlight/releases/tag/v1.0.0
