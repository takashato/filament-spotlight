<?php

declare(strict_types=1);

use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;
use Takashato\FilamentSpotlight\Sources\FilamentResourceSource;
use Takashato\FilamentSpotlight\Tests\Fixtures\FakeFilamentResource;

beforeEach(function (): void {
    FakeFilamentResource::reset();
    FakeFilamentResource::$resolveRecordReturnsRecord = true;
    config()->set('spotlight.sources.'.FilamentResourceSource::class.'.actions.enabled', true);
});

function recordPayload(string $key = '1'): array
{
    return [
        'kind' => 'record',
        'resource' => FakeFilamentResource::class,
        'key' => $key,
        'title' => 'Some Record',
    ];
}

it('returns empty array when actions config flag is disabled', function (): void {
    config()->set('spotlight.sources.'.FilamentResourceSource::class.'.actions.enabled', false);
    FakeFilamentResource::$actionsResolver = fn (): array => [Action::make('edit')];

    $source = new FilamentResourceSource(
        resourcesResolver: fn (): array => [FakeFilamentResource::class],
    );

    expect($source->resolveActionsFor('rid', recordPayload()))->toBe([]);
});

it('returns empty array when payload kind is not record', function (): void {
    FakeFilamentResource::$actionsResolver = fn (): array => [Action::make('edit')];

    $source = new FilamentResourceSource(
        resourcesResolver: fn (): array => [FakeFilamentResource::class],
    );

    $payload = recordPayload();
    $payload['kind'] = 'shortcut';

    expect($source->resolveActionsFor('rid', $payload))->toBe([]);
});

it('returns empty array when record cannot be resolved', function (): void {
    FakeFilamentResource::$resolveRecordReturnsRecord = false;
    FakeFilamentResource::$actionsResolver = fn (): array => [Action::make('edit')];

    $source = new FilamentResourceSource(
        resourcesResolver: fn (): array => [FakeFilamentResource::class],
    );

    expect($source->resolveActionsFor('rid', recordPayload()))->toBe([]);
});

it('returns empty array when permission is denied', function (): void {
    FakeFilamentResource::$canView = false;
    FakeFilamentResource::$actionsResolver = fn (): array => [Action::make('edit')];

    $source = new FilamentResourceSource(
        resourcesResolver: fn (): array => [FakeFilamentResource::class],
    );

    expect($source->resolveActionsFor('rid', recordPayload()))->toBe([]);
});

it('returns empty array when resource throws inside getGlobalSearchResultActions', function (): void {
    FakeFilamentResource::$actionsResolver = function (): array {
        throw new RuntimeException('boom');
    };

    $source = new FilamentResourceSource(
        resourcesResolver: fn (): array => [FakeFilamentResource::class],
    );

    expect($source->resolveActionsFor('rid', recordPayload()))->toBe([]);
});

it('returns namespaced actions when resource provides them', function (): void {
    FakeFilamentResource::$actionsResolver = fn (Model $r): array => [
        Action::make('edit'),
        Action::make('delete'),
    ];

    $source = new FilamentResourceSource(
        resourcesResolver: fn (): array => [FakeFilamentResource::class],
    );

    $actions = $source->resolveActionsFor('row-42', recordPayload());

    expect($actions)->toHaveCount(2);
    expect($actions[0]->getName())->toBe('spotlight::row-42::edit');
    expect($actions[1]->getName())->toBe('spotlight::row-42::delete');
});

it('renders resolved actions as borderless link-style buttons', function (): void {
    FakeFilamentResource::$actionsResolver = fn (Model $r): array => [
        Action::make('edit'),
        Action::make('delete'),
    ];

    $source = new FilamentResourceSource(
        resourcesResolver: fn (): array => [FakeFilamentResource::class],
    );

    $actions = $source->resolveActionsFor('row-42', recordPayload());

    expect($actions[0]->isLink())->toBeTrue();
    expect($actions[1]->isLink())->toBeTrue();
});

it('filters out hidden actions', function (): void {
    FakeFilamentResource::$actionsResolver = fn (): array => [
        Action::make('visible'),
        Action::make('hidden')->visible(false),
    ];

    $source = new FilamentResourceSource(
        resourcesResolver: fn (): array => [FakeFilamentResource::class],
    );

    $actions = $source->resolveActionsFor('rid', recordPayload());

    expect($actions)->toHaveCount(1);
    expect($actions[0]->getName())->toBe('spotlight::rid::visible');
});

it('drops a single throwing action without breaking the rest', function (): void {
    FakeFilamentResource::$actionsResolver = fn (): array => [
        Action::make('ok-1'),
        Action::make('boom')->visible(function (): bool {
            throw new RuntimeException('isVisible failed');
        }),
        Action::make('ok-2'),
    ];

    $source = new FilamentResourceSource(
        resourcesResolver: fn (): array => [FakeFilamentResource::class],
    );

    $actions = $source->resolveActionsFor('rid', recordPayload());

    expect($actions)->toHaveCount(2);
    expect($actions[0]->getName())->toBe('spotlight::rid::ok-1');
    expect($actions[1]->getName())->toBe('spotlight::rid::ok-2');
});

it('namespaces collide-prone names distinctly across two result IDs', function (): void {
    FakeFilamentResource::$actionsResolver = fn (): array => [Action::make('edit')];

    $source = new FilamentResourceSource(
        resourcesResolver: fn (): array => [FakeFilamentResource::class],
    );

    $a = $source->resolveActionsFor('row-1', recordPayload('1'));
    $b = $source->resolveActionsFor('row-2', recordPayload('2'));

    expect($a[0]->getName())->toBe('spotlight::row-1::edit');
    expect($b[0]->getName())->toBe('spotlight::row-2::edit');
    expect($a[0]->getName())->not->toBe($b[0]->getName());
});

it('flags has_actions on payload when resource overrides getGlobalSearchResultActions', function (): void {
    FakeFilamentResource::$actionsResolver = fn (): array => [Action::make('edit')];
    FakeFilamentResource::$rows = [
        ['title' => 'One', 'url' => '/things/1'],
    ];

    $source = new FilamentResourceSource(
        resourcesResolver: fn (): array => [FakeFilamentResource::class],
    );

    $results = $source->search('one', 5);

    expect($results->first()->payload()['has_actions'] ?? false)->toBeTrue();
});
