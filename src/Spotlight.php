<?php

declare(strict_types=1);

namespace Takashato\FilamentSpotlight;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Takashato\FilamentSpotlight\Contracts\SpotlightSource;
use Takashato\FilamentSpotlight\Models\SpotlightRecent;

/**
 * Source registry. Singleton; resolved through the container.
 *
 * Sources may be registered as class strings (resolved lazily via the container)
 * or as concrete instances. Disabled and duplicate keys are filtered at resolve time.
 */
class Spotlight
{
    /**
     * @var array<int, class-string<SpotlightSource>|SpotlightSource>
     */
    protected array $sources = [];

    public function __construct(protected Container $container) {}

    /**
     * @param  class-string<SpotlightSource>|SpotlightSource  $source
     */
    public function registerSource(string|SpotlightSource $source): static
    {
        if (is_string($source) && ! is_subclass_of($source, SpotlightSource::class)) {
            throw new InvalidArgumentException(
                sprintf('Class [%s] must implement %s.', $source, SpotlightSource::class),
            );
        }

        $this->sources[] = $source;

        return $this;
    }

    /**
     * Resolve, filter and sort all registered sources.
     *
     * @return Collection<int, SpotlightSource>
     */
    public function sources(): Collection
    {
        $resolved = collect($this->sources)
            ->map(fn ($s): SpotlightSource => is_string($s) ? $this->container->make($s) : $s)
            ->filter(fn (SpotlightSource $s): bool => $s->isEnabled())
            ->unique(fn (SpotlightSource $s): string => $s->key())
            ->sortByDesc(fn (SpotlightSource $s): int => $s->priority())
            ->values();

        return $resolved;
    }

    public function flush(): void
    {
        $this->sources = [];
    }

    /**
     * Record a visited result for a user. UPSERTs by `(user_id, source_key, result_id)`
     * and prunes anything beyond the per-user cap.
     *
     * SECURITY: callers must pass a server-trusted `$userId` (e.g. `Filament::auth()->id()`).
     *
     * @param  array<string, mixed>  $payload
     */
    public function recordVisit(int $userId, string $sourceKey, string $resultId, string $title, array $payload = []): void
    {
        if ($userId <= 0 || $sourceKey === '' || $resultId === '' || $title === '') {
            return;
        }

        SpotlightRecent::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'source_key' => $sourceKey,
                'result_id' => $resultId,
            ],
            [
                'title' => $title,
                'payload' => $payload,
                'visited_at' => Carbon::now(),
            ],
        );

        $cap = (int) (config('spotlight.recents.cap_per_user') ?? 50);
        if ($cap <= 0) {
            return;
        }

        $cutoff = SpotlightRecent::query()
            ->where('user_id', $userId)
            ->orderByDesc('visited_at')
            ->orderByDesc('id')
            ->skip($cap)
            ->take(1)
            ->value('visited_at');

        if ($cutoff !== null) {
            SpotlightRecent::query()
                ->where('user_id', $userId)
                ->where('visited_at', '<=', $cutoff)
                ->delete();
        }
    }
}
