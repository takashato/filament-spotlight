<?php

declare(strict_types=1);

namespace Takashato\FilamentSpotlight\DTOs;

use Takashato\FilamentSpotlight\Contracts\SpotlightResult;

/**
 * Default `SpotlightResult` implementation. Sources may construct directly or
 * implement the contract themselves for richer behavior.
 */
final readonly class Result implements SpotlightResult
{
    /**
     * @param  array<string, mixed>  $handler
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $sourceKey,
        public array $handler,
        public ?string $subtitle = null,
        public ?string $icon = null,
        public ?string $badge = null,
    ) {}

    public function id(): string
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function subtitle(): ?string
    {
        return $this->subtitle;
    }

    public function icon(): ?string
    {
        return $this->icon;
    }

    public function badge(): ?string
    {
        return $this->badge;
    }

    public function sourceKey(): string
    {
        return $this->sourceKey;
    }

    public function handler(): array
    {
        return $this->handler;
    }
}
