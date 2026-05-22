<?php

declare(strict_types=1);

namespace Takashato\FilamentSpotlight\Tests\Fixtures;

use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Collection;
use Takashato\FilamentSpotlight\Contracts\AsyncSpotlightSource;
use Takashato\FilamentSpotlight\DTOs\Handler;
use Takashato\FilamentSpotlight\DTOs\Result;

class FakeAsyncSource implements AsyncSpotlightSource
{
    /**
     * @param  array<int, array{id: string, title: string}>  $items
     */
    public function __construct(
        public string $key = 'fake-async',
        public int $priority = 60,
        public bool $enabled = true,
        public array $items = [],
        public bool $rejects = false,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return 'Fake Async';
    }

    public function icon(): ?string
    {
        return null;
    }

    public function priority(): int
    {
        return $this->priority;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function debounceMs(): int
    {
        return 200;
    }

    public function searchAsync(string $query, int $limit): PromiseInterface
    {
        if ($this->rejects) {
            return Create::rejectionFor(new \RuntimeException('upstream down'));
        }

        return Create::promiseFor($this->buildResults($query, $limit));
    }

    public function search(string $query, int $limit): Collection
    {
        return $this->buildResults($query, $limit);
    }

    public function empty(int $limit): Collection
    {
        return collect();
    }

    /**
     * @return Collection<int, Result>
     */
    protected function buildResults(string $query, int $limit): Collection
    {
        return collect($this->items)
            ->filter(fn (array $i): bool => $query === '' || str_contains(strtolower($i['title']), strtolower($query)))
            ->map(fn (array $i): Result => new Result(
                id: $i['id'],
                title: $i['title'],
                sourceKey: $this->key,
                handler: Handler::url('/'.$this->key.'/'.$i['id']),
            ))
            ->values()
            ->take($limit);
    }
}
