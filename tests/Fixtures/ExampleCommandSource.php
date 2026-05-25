<?php

declare(strict_types=1);

namespace Takashato\FilamentSpotlight\Tests\Fixtures;

use Illuminate\Support\Collection;
use Takashato\FilamentSpotlight\Contracts\SpotlightResult;
use Takashato\FilamentSpotlight\Contracts\SpotlightSource;
use Takashato\FilamentSpotlight\DTOs\Handler;
use Takashato\FilamentSpotlight\DTOs\Result;

/**
 * Reference source used by docs/sources.md. Keep < 30 LOC of body.
 *
 * Not registered in default config — consumers copy/paste this verbatim
 * to scaffold their own search-by-keyword command source.
 */
final class ExampleCommandSource implements SpotlightSource
{
    /** @var array<int, array{id: string, title: string}> */
    private array $commands = [
        ['id' => 'logout', 'title' => 'Sign out'],
        ['id' => 'profile', 'title' => 'Open profile'],
    ];

    public function key(): string
    {
        return 'commands';
    }

    public function label(): string
    {
        return 'Commands';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-command-line';
    }

    public function priority(): int
    {
        return 80;
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function search(string $query, int $limit): Collection
    {
        $needle = mb_strtolower(trim($query));

        return collect($this->commands)
            ->filter(fn (array $c): bool => $needle === '' || str_contains(mb_strtolower($c['title']), $needle))
            ->take($limit)
            ->map(fn (array $c): SpotlightResult => new Result(
                id: $c['id'],
                title: $c['title'],
                sourceKey: $this->key(),
                handler: Handler::callback($this->key(), $c['id']),
            ))
            ->values();
    }

    public function empty(int $limit): Collection
    {
        return collect();
    }
}
