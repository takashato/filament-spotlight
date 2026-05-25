# Handler Directives

A handler is the action that fires when the user activates a result (Enter, click, or tap). Handlers are **serializable directive arrays**, never closures. The palette renderer (Livewire today, JS clients tomorrow) interprets them.

This separation matters: results travel from PHP to the wire as JSON, recents are persisted with their handler intact, and headless clients can dispatch handlers without invoking PHP at all.

Build directives via the `Handler` factory:

```php
use Takashato\FilamentSpotlight\DTOs\Handler;
```

## `url`

Navigate the browser to a URL.

```php
Handler::url('/admin/shipments/42');
Handler::url('/admin/shipments/42', target: '_blank');
```

Output:

```php
['type' => 'url', 'url' => '/admin/shipments/42', 'target' => '_self']
```

**Security:** the palette only accepts relative URLs or same-origin URLs. `javascript:`, `data:`, `file:`, protocol-relative (`//evil.tld`), and external https hosts are rejected at handler-validation time. See `tests/Feature/SpotlightPaletteTest.php` for the allowlist test cases.

If you need to send a user to a third-party host, render a Filament page that performs a server-side redirect after authorization checks. Don't shortcut through `url`.

## `event`

Dispatch a Livewire / JS event with a JSON-serializable payload.

```php
Handler::event('shipment:focus', ['id' => 42]);
```

Output:

```php
[
    'type' => 'event',
    'name' => 'shipment:focus',
    'payload' => ['id' => 42],
]
```

**Payload constraints:** values must be scalar (string, int, float, bool, null) or arrays of scalars. Objects, closures, and resources are rejected. The validator runs before dispatch — see `it rejects event payloads containing non-scalar values` in the palette tests.

Use `event` for cross-component coordination: focus an existing Livewire component, refresh a data table, mark a notification read.

## `modal`

Open a Livewire modal component.

```php
Handler::modal('app.profile-modal', ['userId' => $user->id]);
```

Output:

```php
[
    'type' => 'modal',
    'component' => 'app.profile-modal',
    'props' => ['userId' => $user->id],
]
```

The palette emits a `spotlight:open-modal` Livewire event with the directive. The host application is responsible for listening and rendering the component — the package does not register modal components for you.

Recommended pattern: a single root `<livewire:spotlight-modal-host />` somewhere in the panel layout that listens for `spotlight:open-modal` and renders the named component.

## `callback`

Emit a `spotlight:source-callback` Livewire event. The originating source is responsible for handling the callback.

```php
Handler::callback('commands', 'logout');
```

Output:

```php
[
    'type' => 'callback',
    'source' => 'commands',
    'id' => 'logout',
]
```

This directive is the contract that v1.1 `CommandSource` will consume. In v1.0 it is shipped, validated, and round-trips through recents — but no built-in source ships handler logic for callbacks. Custom sources may consume it today by listening for `spotlight:source-callback` in their own Livewire component.

## Choosing a directive

| Use case | Directive |
| --- | --- |
| "Open this resource detail page" | `url` |
| "Focus an item already on the current page" | `event` |
| "Pop a quick-edit modal" | `modal` |
| "Run an in-app command (logout, switch tenant, toggle theme)" | `callback` |

When in doubt, prefer `url` — it works without a JS handler on the host side.

## Round-tripping

Every directive must survive `json_encode` / `json_decode`. The package test suite asserts this for all four types:

```php
expect(json_decode((string) json_encode($directive), true))->toBe($directive);
```

If you find yourself needing closures, escape hatches, or instance-bound state in a handler, refactor to either an `event` (with the bare identifier in the payload) or a `url` to a dedicated controller / Filament page that performs the action server-side.
