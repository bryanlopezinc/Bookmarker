<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property string|null $description
 * @property string $name
 * @property array $settings
 * @property int $visibility
 * @property int $user_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property int bookmarksCount
 * @method static Builder|QueryBuilder onlyAttributes(array $attributes = [])
 */
final class Folder extends Model
{
    /**
     * {@inheritdoc}
     */
    protected $table = 'folders';

    /**
     * {@inheritdoc}
     */
    protected $guarded = [];

    /**
     * {@inheritdoc}
     */
    protected $casts = [
        'visibility' => 'int',
        'settings'   => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $builder
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOnlyAttributes($builder, array $attributes = [])
    {
        $attributes = collect($attributes)->mapWithKeys(fn (string $col) => [$col => $col]);

        if ($attributes->isEmpty()) {
            $builder->addSelect('folders.*');
        }

        if (!$attributes->isEmpty()) {
            $builder->addSelect(
                $this->qualifyColumns($attributes->except(['bookmarks_count', 'settings'])->all())
            );
        }

        $this->parseBookmarksCountRelationQuery($builder, $attributes);

        $this->parseSettingsQuery($builder, $attributes);

        return $builder;
    }

    /**
     * @param Builder $builder
     *
     * @return Builder
     */
    protected function parseBookmarksCountRelationQuery(&$builder, Collection $attributes)
    {
        $wantsBookmarksCount = $attributes->has('bookmarks_count') ?: $attributes->isEmpty();

        if (!$wantsBookmarksCount) {
            return $builder;
        }

        return $builder
            ->addSelect([
                'bookmarksCount' => FolderBookmark::query()
                    ->selectRaw("COUNT(*)")
                    ->whereRaw("folder_id = {$this->qualifyColumn('id')}")
            ]);
    }

    /**
     * @param Builder $builder
     *
     * @return Builder
     */
    protected function parseSettingsQuery(&$builder, Collection $attributes)
    {
        $wantsSettings = $attributes->has('settings') ?: $attributes->isEmpty();

        if (!$wantsSettings) {
            return $builder;
        }

        return $builder
            ->addSelect([
                'settings' => FolderSetting::query()
                    ->select(DB::raw("ifNULL(JSON_ARRAYAGG(JSON_OBJECT('key', `key`, 'value', `value`)), '{}')"))
                    ->whereRaw("folder_id = {$this->qualifyColumn('id')}")
            ]);
    }
}
