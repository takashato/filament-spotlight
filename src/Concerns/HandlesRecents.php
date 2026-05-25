<?php

declare(strict_types=1);

namespace Takashato\FilamentSpotlight\Concerns;

use Takashato\FilamentSpotlight\Contracts\SpotlightResult;

/**
 * Default implementation for sources that opt into `RecentsAware` but do not
 * yet have permission/visibility logic to re-validate captured rows. Returning
 * `null` causes the row to be dropped from the recents group on read.
 */
trait HandlesRecents
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function validateRecent(string $resultId, array $payload): ?SpotlightResult
    {
        return null;
    }
}
