<?php

declare(strict_types=1);

namespace Takashato\FilamentSpotlight\Contracts;

/**
 * Optional contract for sources that participate in Recents. The engine
 * persists `(sourceKey, resultId, title, payload)` on visit; re-validation
 * happens at read time so revoked-access records never leak.
 *
 * Sources MAY also use `Concerns\HandlesRecents` for a default null impl.
 */
interface RecentsAware
{
    /**
     * Re-resolve a previously-visited result against current permissions.
     * Return `null` when the record is gone or unauthorized — the engine
     * will silently drop the row from the recents group.
     *
     * @param  array<string, mixed>  $payload
     */
    public function validateRecent(string $resultId, array $payload): ?SpotlightResult;
}
