<?php

declare(strict_types=1);

namespace Takashato\FilamentSpotlight\Contracts;

use Illuminate\Support\Collection;

/**
 * A pluggable Spotlight source. Implementations self-enforce permissions —
 * results returned MUST already be visible to the current authenticated user.
 */
interface SpotlightSource
{
    /**
     * Stable identifier (e.g. 'resources', 'nav'). Used as group key + recents key.
     */
    public function key(): string;

    /**
     * Human-readable group label. Should be translated via `__()`.
     */
    public function label(): string;

    /**
     * Heroicon name (e.g. 'heroicon-o-rectangle-stack'), or null for none.
     */
    public function icon(): ?string;

    /**
     * Higher = shown first. Built-ins use 100 (resources), 90 (nav), 70 (commands).
     */
    public function priority(): int;

    /**
     * Per-panel/user toggle. Engine skips disabled sources before calling search/empty.
     */
    public function isEnabled(): bool;

    /**
     * @return Collection<int, SpotlightResult>
     */
    public function search(string $query, int $limit): Collection;

    /**
     * Empty-state results when query is blank.
     *
     * @return Collection<int, SpotlightResult>
     */
    public function empty(int $limit): Collection;
}
