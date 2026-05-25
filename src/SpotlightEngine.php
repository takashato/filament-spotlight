<?php

declare(strict_types=1);

namespace Takashato\FilamentSpotlight;

use Filament\Facades\Filament;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils as PromiseUtils;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use Takashato\FilamentSpotlight\Contracts\AsyncSpotlightSource;
use Takashato\FilamentSpotlight\Contracts\SpotlightResult;
use Takashato\FilamentSpotlight\Contracts\SpotlightSource;
use Takashato\FilamentSpotlight\Recents\RecentsContributor;
use Throwable;

/**
 * Search orchestrator. Pure read-only: no DB writes, no events, no side effects.
 *
 * Returns a `Collection<sourceKey, Collection<SpotlightResult>>` so the renderer
 * can group + sort by source priority order (already applied here).
 */
class SpotlightEngine
{
    public function __construct(
        protected Container $container,
        protected Spotlight $registry,
    ) {}

    /**
     * @return Collection<string, Collection<int, SpotlightResult>>
     */
    public function search(string $query, ?int $limit = null): Collection
    {
        $sources = $this->registry->sources();
        if ($sources->isEmpty()) {
            return collect();
        }

        $perSource = $this->perSourceLimit();
        $totalLimit = $limit ?? $this->totalLimit();

        [$async, $sync] = $sources->partition(fn (SpotlightSource $s): bool => $s instanceof AsyncSpotlightSource);

        /** @var array<string, Collection<int, SpotlightResult>> $groups */
        $groups = [];

        foreach ($sync as $source) {
            $groups[$source->key()] = $this->runSource(
                fn () => $source->search($query, $perSource),
                $perSource,
            );
        }

        if ($async->isNotEmpty()) {
            /** @var array<string, PromiseInterface> $promises */
            $promises = [];
            foreach ($async as $source) {
                /** @var AsyncSpotlightSource $source */
                $promises[$source->key()] = $source->searchAsync($query, $perSource);
            }
            $settled = PromiseUtils::settle($promises)->wait();
            foreach ($settled as $key => $outcome) {
                if (($outcome['state'] ?? null) === 'fulfilled') {
                    $groups[$key] = $this->coerceCollection($outcome['value'])->take($perSource);
                } else {
                    $groups[$key] = collect();
                }
            }
        }

        $ordered = $sources
            ->mapWithKeys(fn (SpotlightSource $s): array => [$s->key() => $groups[$s->key()] ?? collect()])
            ->pipe(fn (Collection $g) => $this->dedupe($g))
            ->pipe(fn (Collection $g) => $this->applyTotalLimit($g, $totalLimit));

        return $ordered;
    }

    /**
     * @return Collection<string, Collection<int, SpotlightResult>>
     */
    public function empty(?int $limit = null): Collection
    {
        $sources = $this->registry->sources();
        if ($sources->isEmpty()) {
            return collect();
        }

        $perSource = $this->perSourceLimit();
        $totalLimit = $limit ?? $this->totalLimit();

        /** @var Collection<string, Collection<int, SpotlightResult>> $groups */
        $groups = $sources->mapWithKeys(fn (SpotlightSource $s): array => [
            $s->key() => $this->runSource(fn () => $s->empty($perSource), $perSource),
        ]);

        $recents = $this->recentsGroup();
        if ($recents !== null) {
            /** @var Collection<string, Collection<int, SpotlightResult>> $groups */
            $groups = collect(['recents' => $recents])->merge($groups);
        }

        return $this->applyTotalLimit($this->dedupe($groups), $totalLimit);
    }

    /**
     * @return Collection<int, SpotlightResult>|null
     */
    protected function recentsGroup(): ?Collection
    {
        if (! (bool) (config('spotlight.recents.enabled') ?? true)) {
            return null;
        }

        $userId = $this->resolveAuthUserId();
        if ($userId === null) {
            return null;
        }

        $limit = (int) (config('spotlight.recents.show_in_empty_state') ?? 5);
        if ($limit <= 0) {
            return null;
        }

        try {
            $contributor = $this->container->make(RecentsContributor::class);
            $results = $contributor->contribute($userId, $limit);
        } catch (Throwable) {
            return null;
        }

        return $results->isEmpty() ? null : $results;
    }

    protected function resolveAuthUserId(): ?int
    {
        try {
            if (class_exists(Filament::class)) {
                $id = Filament::auth()->id();
                if (is_int($id) && $id > 0) {
                    return $id;
                }
                if (is_string($id) && ctype_digit($id)) {
                    return (int) $id;
                }
            }
        } catch (Throwable) {
            // fall through to default guard
        }

        try {
            $id = auth()->id();
            if (is_int($id) && $id > 0) {
                return $id;
            }
            if (is_string($id) && ctype_digit($id)) {
                return (int) $id;
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    /**
     * @param  callable(): mixed  $callback
     * @return Collection<int, SpotlightResult>
     */
    protected function runSource(callable $callback, int $limit): Collection
    {
        try {
            $result = $callback();
        } catch (Throwable) {
            return collect();
        }

        return $this->coerceCollection($result)->take($limit);
    }

    /**
     * @return Collection<int, SpotlightResult>
     */
    protected function coerceCollection(mixed $value): Collection
    {
        if ($value instanceof Collection) {
            return $value->values()->filter(fn ($r): bool => $r instanceof SpotlightResult)->values();
        }
        if (is_array($value)) {
            return collect($value)->filter(fn ($r): bool => $r instanceof SpotlightResult)->values();
        }

        return collect();
    }

    /**
     * @param  Collection<string, Collection<int, SpotlightResult>>  $groups
     * @return Collection<string, Collection<int, SpotlightResult>>
     */
    protected function dedupe(Collection $groups): Collection
    {
        $seen = [];

        return $groups->map(function (Collection $results) use (&$seen): Collection {
            return $results->filter(function (SpotlightResult $r) use (&$seen): bool {
                $key = $r->sourceKey().'::'.$r->id();
                if (isset($seen[$key])) {
                    return false;
                }
                $seen[$key] = true;

                return true;
            })->values();
        });
    }

    /**
     * Trim to total cap by walking groups in priority order (already sorted).
     *
     * @param  Collection<string, Collection<int, SpotlightResult>>  $groups
     * @return Collection<string, Collection<int, SpotlightResult>>
     */
    protected function applyTotalLimit(Collection $groups, int $totalLimit): Collection
    {
        if ($totalLimit <= 0) {
            /** @var Collection<string, Collection<int, SpotlightResult>> $empty */
            $empty = $groups->map(fn (): Collection => collect());

            return $empty;
        }

        $remaining = $totalLimit;
        /** @var array<string, Collection<int, SpotlightResult>> $trimmed */
        $trimmed = [];
        foreach ($groups as $key => $results) {
            if ($remaining <= 0) {
                $trimmed[$key] = collect();

                continue;
            }
            $trimmed[$key] = $results->take($remaining)->values();
            $remaining -= $trimmed[$key]->count();
        }

        return collect($trimmed);
    }

    protected function perSourceLimit(): int
    {
        return (int) (config('spotlight.limits.per_source') ?? 5);
    }

    protected function totalLimit(): int
    {
        return (int) (config('spotlight.limits.total') ?? 20);
    }
}
