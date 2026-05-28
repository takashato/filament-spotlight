<?php

declare(strict_types=1);

namespace Takashato\FilamentSpotlight\Tests\Fixtures;

use Closure;
use Filament\Actions\Action;
use Filament\GlobalSearch\GlobalSearchResult;
use Filament\Resources\Resource;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Test fixture: a Filament resource that bypasses panel-coupled checks and
 * returns controllable global-search results. Static state is mutable so tests
 * can flip permission and adjust results per scenario.
 */
class FakeFilamentResource extends Resource
{
    protected static ?string $model = null;

    public static bool $canSearch = true;

    public static bool $canView = true;

    /** @var array<int, array{title: string, url: string, details?: array<string, string>}> */
    public static array $rows = [];

    public static ?string $navigationLabel = 'Fake';

    public static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    /** @var Closure(Model): array<int, Action>|null */
    public static ?Closure $actionsResolver = null;

    public static bool $resolveRecordReturnsRecord = false;

    public static function reset(): void
    {
        self::$canSearch = true;
        self::$canView = true;
        self::$rows = [];
        self::$navigationLabel = 'Fake';
        self::$navigationIcon = 'heroicon-o-cube';
        self::$actionsResolver = null;
        self::$resolveRecordReturnsRecord = false;
    }

    public static function canView(Model $record): bool
    {
        return static::$canView;
    }

    public static function getGlobalSearchResultActions(Model $record): array
    {
        if (self::$actionsResolver === null) {
            return [];
        }

        return (self::$actionsResolver)($record);
    }

    public static function resolveRecordRouteBinding(int|string $key, ?Closure $modifyQuery = null): ?Model
    {
        if (! self::$resolveRecordReturnsRecord) {
            return null;
        }

        // Return a transient anonymous model that satisfies the type contract.
        $model = new class extends Model
        {
            protected $table = 'fake_records';

            public $timestamps = false;
        };

        $model->forceFill(['id' => $key])->setRawAttributes(['id' => $key], true);

        return $model;
    }

    public static function canGloballySearch(): bool
    {
        return static::$canSearch;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['title'];
    }

    public static function getGlobalSearchResults(string $search): Collection
    {
        $needle = strtolower($search);

        return collect(static::$rows)
            ->filter(fn (array $r): bool => str_contains(strtolower($r['title']), $needle))
            ->map(function (array $r): GlobalSearchResult {
                $record = static::buildSearchRecord($r);
                $actions = self::$actionsResolver !== null
                    ? array_map(
                        fn (Action $action): Action => $action->hasRecord() ? $action : $action->record($record),
                        (self::$actionsResolver)($record),
                    )
                    : [];

                return new GlobalSearchResult(
                    title: $r['title'],
                    url: $r['url'],
                    details: $r['details'] ?? [],
                    actions: $actions,
                );
            })
            ->values();
    }

    /**
     * @param  array{title: string, url: string, details?: array<string, string>, key?: int|string}  $row
     */
    protected static function buildSearchRecord(array $row): Model
    {
        $key = $row['key'] ?? 0;
        $model = new class extends Model
        {
            protected $table = 'fake_records';

            public $timestamps = false;

            protected $primaryKey = 'id';
        };

        $model->forceFill(['id' => $key]);

        return $model;
    }

    public static function getNavigationLabel(): string
    {
        return static::$navigationLabel;
    }

    public static function getNavigationIcon(): \BackedEnum|Htmlable|string|null
    {
        return static::$navigationIcon;
    }

    public static function getUrl(?string $name = null, array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?Model $tenant = null, bool $shouldGuessMissingParameters = false, ?string $configuration = null): string
    {
        return '/fake-resource';
    }
}
