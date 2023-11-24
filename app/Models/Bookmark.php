<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property string|null $description
 * @property string $title
 * @property bool $has_custom_title
 * @property bool $description_set_by_user
 * @property string $url
 * @property string|null $preview_image_url
 * @property int $user_id
 * @property int $source_id
 * @property string $url_canonical
 * @property string $url_canonical_hash
 * @property string $resolved_url
 * @property Source $source
 * @property EloquentCollection<Tag> $tags
 * @property bool $isHealthy
 * @property bool $isUserFavorite
 * @property bool $hasDuplicates
 * @property \Carbon\Carbon|null $resolved_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @method static Builder|QueryBuilder WithQueryOptions(array $attributes = [])
 */
final class Bookmark extends Model
{
    public const DESCRIPTION_MAX_LENGTH = 200;
    public const TITLE_MAX_LENGTH       = 100;

    /**
     * {@inheritdoc}
     */
    protected $table = 'bookmarks';

    /**
     * {@inheritdoc}
     */
    protected $guarded = [];

    /**
     * {@inheritdoc}
     */
    protected $casts = [
        'has_custom_title'        => 'bool',
        'description_set_by_user' => 'bool',
        'hasDuplicates'           => 'bool',
        'resolved_at'             => 'datetime',
        'created_at'              => 'datetime',
        'updated_at'              => 'datetime',
    ];

    public function getSourceAttribute(): Source
    {
        if ($this->relationLoaded('source')) {
            return $this->relations['source'];
        }

        $model = new Source(
            json_decode($this->attributes['source'], true, JSON_THROW_ON_ERROR)
        );

        $this->setRelation('source', $model);

        return $model;
    }

    protected function isHealthy(): Attribute
    {
        return new Attribute(
            get: function (?int $statusCode) {
                if ($statusCode === null) {
                    return true;
                }

                return $statusCode >= 200 && $statusCode < 300;
            },
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function tags(): HasManyThrough
    {
        return $this->hasManyThrough(Tag::class, Taggable::class, 'taggable_id', 'id', 'id', 'tag_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class, 'source_id', 'id');
    }

    /**
     * @param Builder $builder
     *
     * @return Builder
     */
    public function scopeWithQueryOptions($builder, array $columns = [])
    {
        $columns = collect($columns)->mapWithKeys(fn (string $col) => [$col => $col]);

        $builder->addSelect($this->getQualifiedKeyName());

        if ($columns->isEmpty()) {
            $builder->addSelect('bookmarks.*');
        }

        if (!$columns->isEmpty()) {
            $builder->addSelect(
                $this->qualifyColumns($columns->except(['tags', 'source'])->all())
            );
        }

        $this->parseTagsRelationQuery($builder, $columns);
        $this->parseSourceRelationQuery($builder, $columns);
        $this->parseHealthCheckQuery($builder, $columns);
        $this->parseHasDuplicatesQuery($builder, $columns);

        return $builder;
    }

    /**
     * @param Builder $builder
     *
     * @return Builder
     */
    protected function parseTagsRelationQuery(&$builder, Collection $options)
    {
        $wantsTags = $options->has('tags') ?: $options->isEmpty();

        if (!$wantsTags) {
            return $builder;
        }

        return $builder->with('tags');
    }

    /**
     * @param Builder $builder
     *
     * @return Builder
     */
    protected function parseSourceRelationQuery(&$builder, Collection $options)
    {
        $wantsSiteRelation = $options->has('source') ?: $options->isEmpty();

        if (!$wantsSiteRelation) {
            return $builder;
        }

        return $builder->addSelect([
            'source' => Source::query()
                ->select(DB::raw("JSON_OBJECT('host', host, 'name', name, 'id', id)"))
                ->whereRaw("id = {$this->qualifyColumn('source_id')}"),
        ]);
    }

    /**
     * @param Builder $builder
     *
     * @return Builder
     */
    protected function parseHealthCheckQuery(&$builder, Collection $options)
    {
        $condition = $options->has('is_dead_link') ?: $options->isEmpty();

        if (!$condition) {
            return $builder;
        }

        return $builder->addSelect([
            'isHealthy' => BookmarkHealth::query()
                ->select('status_code')
                ->whereRaw("bookmark_id = {$this->qualifyColumn('id')}")
        ]);
    }

    /**
     * @param Builder $builder
     *
     * @return Builder
     */
    protected function parseHasDuplicatesQuery(&$builder, Collection $options)
    {
        $condition = $options->has('has_duplicates') ?: $options->isEmpty();

        if (!$condition) {
            return $builder;
        }

        $query = <<<SQL
            SELECT
                EXISTS(
                    SELECT b.id
                    from bookmarks b
                    WHERE bookmarks.url_canonical_hash = b.url_canonical_hash
                    AND bookmarks.id != b.id)
        SQL;

        return $builder->selectSub($query, 'hasDuplicates');
    }
}
