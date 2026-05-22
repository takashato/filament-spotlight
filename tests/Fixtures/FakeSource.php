<?php

declare(strict_types=1);

namespace Takashato\FilamentSpotlight\Tests\Fixtures;

use Illuminate\Support\Collection;
use Takashato\FilamentSpotlight\Contracts\SpotlightSource;
use Takashato\FilamentSpotlight\DTOs\Handler;
use Takashato\FilamentSpotlight\DTOs\Result;

class FakeSource implements SpotlightSource
{
    /**
     * @param  array<int, array{id: string, title: string}>  $items
     */
    public function __construct(
        public string $key = 'fake',
        public string $label = 'Fake',
        public int $priority = 50,
        public bool $enabled = true,
        public array $items = [],
        public int $sleepMicroseconds = 0,
        public ?\Throwable $throws = null,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return $this->label;
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

    public function search(string $query, int $limit): Collection
    {
        if ($this->throws) {
            throw $this->throws;
        }
        if ($this->sleepMicroseconds > 0) {
            usleep($this->sleepMicroseconds);
        }

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

    public function empty(int $limit): Collection
    {
        return collect($this->items)
            ->take($limit)
            ->map(fn (array $i): Result => new Result(
                id: $i['id'],
                title: $i['title'],
                sourceKey: $this->key,
                handler: Handler::url('/'.$this->key.'/'.$i['id']),
            ))
            ->values();
    }
}
