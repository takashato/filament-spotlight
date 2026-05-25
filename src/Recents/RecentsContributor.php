<?php

declare(strict_types=1);

namespace Takashato\FilamentSpotlight\Recents;

use Illuminate\Support\Collection;
use Takashato\FilamentSpotlight\Contracts\RecentsAware;
use Takashato\FilamentSpotlight\Contracts\SpotlightResult;
use Takashato\FilamentSpotlight\Contracts\SpotlightSource;
use Takashato\FilamentSpotlight\Models\SpotlightRecent;
use Takashato\FilamentSpotlight\Spotlight;
use Throwable;

/**
 * Builds the recents group for the empty state.
 *
 * - Loads up to `2 * $limit` rows for the user (overscan absorbs dropped rows).
 * - Re-validates each row through the originating source — silently drops
 *   rows the source cannot resolve or the user is no longer allowed to view.
 * - Caps at `$limit`.
 */
class RecentsContributor
{
    public function __construct(protected Spotlight $registry) {}

    /**
     * @return Collection<int, SpotlightResult>
     */
    public function contribute(int $userId, int $limit): Collection
    {
        if ($userId <= 0 || $limit <= 0) {
            return collect();
        }

        $overscan = max($limit * 2, $limit + 5);

        /** @var Collection<int, SpotlightRecent> $rows */
        $rows = SpotlightRecent::query()
            ->where('user_id', $userId)
            ->orderByDesc('visited_at')
            ->orderByDesc('id')
            ->take($overscan)
            ->get();

        if ($rows->isEmpty()) {
            return collect();
        }

        /** @var array<string, SpotlightSource> $sources */
        $sources = $this->registry->sources()
            ->mapWithKeys(fn (SpotlightSource $s): array => [$s->key() => $s])
            ->all();

        /** @var Collection<int, SpotlightResult> $out */
        $out = collect();

        foreach ($rows as $row) {
            if ($out->count() >= $limit) {
                break;
            }

            $source = $sources[$row->source_key] ?? null;
            if (! $source instanceof RecentsAware) {
                continue;
            }

            try {
                $result = $source->validateRecent(
                    $row->result_id,
                    is_array($row->payload) ? $row->payload : [],
                );
            } catch (Throwable) {
                continue;
            }

            if ($result instanceof SpotlightResult) {
                $out->push($result);
            }
        }

        return $out->values();
    }
}
