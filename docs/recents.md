# Recents

The Spotlight palette persists a small per-user list of recently activated results. Recents render at the top of the empty state (when the input is blank) and are scoped strictly per `Filament::auth()->id()`.

## How capture works

1. The palette dispatches a browser `spotlight:result-visited` event when the user activates a result, and immediately calls `$wire.recordVisit($event)` on the Livewire component (the browser event is a public hook for host integrations; it is **not** the trigger for capture).
2. The Livewire component receives the call and forwards to `Spotlight::recordVisit($userId, $sourceKey, $resultId, $title, $payload)`.
3. The user id passed to `recordVisit` is **server-trusted** — it is read from `Filament::auth()->id()`, never from the client payload. This prevents one user from forging recents into another user's row.
4. The row is upserted by `(user_id, source_key, result_id)`. Repeated visits update `visited_at` instead of creating duplicates.
5. After upsert, the LRU is enforced: rows beyond `recents.cap_per_user` (default 50) are deleted.

## Read-time re-validation

When the empty state is rendered, the engine fans out the captured rows to their originating sources for re-validation. This guarantees that revoked-access records, soft-deleted records, and renamed entries don't leak.

A source opts in by implementing `RecentsAware`:

```php
namespace Takashato\FilamentSpotlight\Contracts;

interface RecentsAware
{
    public function validateRecent(string $resultId, array $payload): ?SpotlightResult;
}
```

Return `null` for revoked or missing rows — the engine drops them silently. Return a fresh `SpotlightResult` for valid rows; the engine uses the freshly returned values, so renames in the source domain show up in recents on the next read.

A source that does not implement `RecentsAware` simply has no recents row rendered. Use `Concerns\HandlesRecents` for a default `null` impl when you want to opt in incrementally.

## Minimal `RecentsAware` source

```php
use Takashato\FilamentSpotlight\Contracts\RecentsAware;
use Takashato\FilamentSpotlight\Contracts\SpotlightResult;
use Takashato\FilamentSpotlight\Contracts\SpotlightSource;
use Takashato\FilamentSpotlight\DTOs\Handler;
use Takashato\FilamentSpotlight\DTOs\Result;

final class ShipmentSource implements SpotlightSource, RecentsAware
{
    // ...key/label/icon/priority/isEnabled/search/empty omitted...

    public function validateRecent(string $resultId, array $payload): ?SpotlightResult
    {
        $shipmentId = $payload['shipment_id'] ?? null;
        if ($shipmentId === null) {
            return null;
        }

        $shipment = Shipment::query()
            ->whereKey($shipmentId)
            ->visibleTo(auth()->user())
            ->first();

        return $shipment
            ? new Result(
                id: $resultId,
                title: $shipment->reference,
                sourceKey: $this->key(),
                handler: Handler::url('/admin/shipments/'.$shipment->id),
            )
            : null;
    }
}
```

Notes:

- `auth()->user()` here refers to the same authenticated user the engine used to scope the read — re-checking `visibleTo` is the security boundary.
- The `$resultId` you pass to `new Result()` should match the original — that's how the recents row is keyed.
- The `$payload` arg holds whatever you stuffed into `Result::$payload` at capture time. Keep it small.

## Payload privacy

Only put **identifiers** in `payload` — never raw record fields. Recents rows are persisted in your database and may surface in error reports, audit trails, and backups. Treat them as cheap pointers, not as a cache of the underlying record.

Good payload:

```php
['shipment_id' => 42]
```

Bad payload:

```php
['shipment' => $shipment->toArray()]
```

## Disabling recents

Two routes:

- Set `recents.enabled => false` in `config/spotlight.php` to suppress the recents group entirely.
- Set `recents.show_in_empty_state => 0` to keep capture but hide the row.

Both options leave the `spotlight_recents` table in place. Drop the table with a custom migration if you need to wipe it.
