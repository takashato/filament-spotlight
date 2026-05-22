<?php

declare(strict_types=1);

namespace Takashato\FilamentSpotlight\Contracts;

use GuzzleHttp\Promise\PromiseInterface;

/**
 * Marker for sources that resolve asynchronously. The engine calls
 * `searchAsync()` and races a per-source timeout (default 500ms) against the
 * promise. Sync `search()` should return results already collected (typically
 * by waiting on the same promise) for fallback / legacy callers.
 */
interface AsyncSpotlightSource extends SpotlightSource
{
    public function searchAsync(string $query, int $limit): PromiseInterface;

    /**
     * Per-source debounce override (ms). Engine respects this when the source is
     * the only async source in the result set.
     */
    public function debounceMs(): int;
}
