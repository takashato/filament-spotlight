<?php

declare(strict_types=1);

namespace Takashato\FilamentSpotlight\Contracts;

/**
 * A single result row. The `handler()` directive is a serializable array —
 * sources MUST NOT call `redirect()` / `dispatch()` / etc. directly.
 */
interface SpotlightResult
{
    /**
     * Stable identifier within the source. Used for dedup + recents key.
     */
    public function id(): string;

    public function title(): string;

    public function subtitle(): ?string;

    public function icon(): ?string;

    public function badge(): ?string;

    public function sourceKey(): string;

    /**
     * Serializable handler directive. See Handler::url(), ::event(), ::modal(), ::callback().
     *
     * @return array<string, mixed>
     */
    public function handler(): array;
}
