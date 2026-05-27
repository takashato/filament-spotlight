<?php

declare(strict_types=1);

use Filament\Actions\Action;
use Takashato\FilamentSpotlight\Livewire\SpotlightPalette;
use Takashato\FilamentSpotlight\Sources\FilamentResourceSource;
use Takashato\FilamentSpotlight\Tests\Fixtures\FakeFilamentResource;

beforeEach(function (): void {
    FakeFilamentResource::reset();
    FakeFilamentResource::$resolveRecordReturnsRecord = true;
    config()->set('spotlight.sources.'.FilamentResourceSource::class.'.actions.enabled', true);

    app()->bind(FilamentResourceSource::class, fn () => new FilamentResourceSource(
        resourcesResolver: fn (): array => [FakeFilamentResource::class],
    ));
});

function recordPayloadForPalette(string $key = '1'): array
{
    return [
        'kind' => 'record',
        'resource' => FakeFilamentResource::class,
        'key' => $key,
        'title' => 'Item '.$key,
    ];
}

it('caches resolved actions on the palette under the result id', function (): void {
    FakeFilamentResource::$actionsResolver = fn (): array => [Action::make('edit')];

    $palette = new SpotlightPalette;
    $palette->loadActionsForFocused('row-1', recordPayloadForPalette());

    $cache = invade($palette)->resolvedActionsCache;

    expect($cache)->toHaveKey('row-1');
    expect($cache['row-1'])->toHaveCount(1);
    expect($cache['row-1'][0]->getName())->toBe('spotlight::row-1::edit');
});

it('returns an empty cache entry when payload kind is not record', function (): void {
    FakeFilamentResource::$actionsResolver = fn (): array => [Action::make('edit')];

    $palette = new SpotlightPalette;
    $palette->loadActionsForFocused('row-1', ['kind' => 'shortcut']);

    expect(invade($palette)->resolvedActionsCache['row-1'])->toBe([]);
});

it('ignores empty result ids', function (): void {
    $palette = new SpotlightPalette;
    $palette->loadActionsForFocused('', recordPayloadForPalette());

    expect(invade($palette)->resolvedActionsCache)->toBe([]);
});

it('actionsForFocused returns the cached actions for the highlighted row', function (): void {
    FakeFilamentResource::$actionsResolver = fn (): array => [Action::make('edit')];

    $palette = new SpotlightPalette;
    $palette->loadActionsForFocused('row-1', recordPayloadForPalette());
    $palette->highlightedId = 'row-1';

    $actions = $palette->actionsForFocused();

    expect($actions)->toHaveCount(1);
    expect($actions[0]->getName())->toBe('spotlight::row-1::edit');
});

it('actionsForFocused returns empty array when no row is highlighted', function (): void {
    $palette = new SpotlightPalette;

    expect($palette->actionsForFocused())->toBe([]);
});

it('clears the action cache when the palette closes', function (): void {
    FakeFilamentResource::$actionsResolver = fn (): array => [Action::make('edit')];

    $palette = new SpotlightPalette;
    $palette->loadActionsForFocused('row-1', recordPayloadForPalette());
    $palette->close();

    expect(invade($palette)->resolvedActionsCache)->toBe([]);
});

it('records an empty cache entry when source throws (does not propagate)', function (): void {
    app()->bind(FilamentResourceSource::class, fn () => new class extends FilamentResourceSource
    {
        public function resolveActionsFor(string $resultId, array $payload): array
        {
            throw new RuntimeException('source exploded');
        }
    });

    $palette = new SpotlightPalette;
    $palette->loadActionsForFocused('row-1', recordPayloadForPalette());

    expect(invade($palette)->resolvedActionsCache['row-1'])->toBe([]);
});

it('keeps actions empty when none are visible', function (): void {
    FakeFilamentResource::$actionsResolver = fn (): array => [
        Action::make('hidden')->visible(false),
    ];

    $palette = new SpotlightPalette;
    $palette->loadActionsForFocused('row-1', recordPayloadForPalette());

    expect(invade($palette)->resolvedActionsCache['row-1'])->toBe([]);
});
