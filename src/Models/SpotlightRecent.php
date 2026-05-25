<?php

declare(strict_types=1);

namespace Takashato\FilamentSpotlight\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Per-user recents row. Re-validation happens at read time via the owning
 * source's `validateRecent()` hook — never trust raw rows for visibility.
 *
 * @property int $id
 * @property int $user_id
 * @property string $source_key
 * @property string $result_id
 * @property string $title
 * @property array<string, mixed>|null $payload
 * @property Carbon $visited_at
 */
class SpotlightRecent extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'source_key',
        'result_id',
        'title',
        'payload',
        'visited_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'payload' => 'array',
            'visited_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        return (string) (config('spotlight.recents.table') ?? 'spotlight_recents');
    }
}
