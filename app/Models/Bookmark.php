<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\TaggableInterface;
use App\Enums\TaggableType;
use App\QueryColumns\BookmarkAttributes;
use App\ValueObjects\ResourceID;
use App\ValueObjects\UserID;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @property int $id
 * @property string|null $description
 * @property string $title
 * @property bool $has_custom_title
 * @property bool $description_set_by_user
 * @property string $url
 * @property string|null $preview_image_url
 * @property int $user_id foreign key to \App\Models\User
 * @property int $source_id foreign key to \App\Models\Source
 * @property string $url_canonical
 * @property string $url_canonical_hash
 * @property string $resolved_url
 *  @property \Carbon\Carbon|null $resolved_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @method static Builder WithQueryOptions(BookmarkAttributes $queryOptions)
 */
final class Bookmark extends Model implements TaggableInterface
{
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
        'has_custom_title' => 'bool',
        'description_set_by_user' => 'bool',
        'is_healthy' => 'bool',
        'resolved_at' => 'datetime'
    ];

    public function tags(): HasManyThrough
    {
        return $this->hasManyThrough(Tag::class, Taggable::class, 'taggable_id', 'id', 'id', 'tag_id')
            ->where('taggable_type', Taggable::BOOKMARK_TYPE);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class, 'source_id', 'id');
    }

    public function taggableID(): ResourceID
    {
        return new ResourceID($this->id);
    }

    public function taggableType(): TaggableType
    {
        return TaggableType::BOOKMARK;
    }

    public function taggedBy(): UserID
    {
        return new UserID($this->user_id);
    }

    /**
     * @param Builder $builder
     *
     * @return Builder
     */
    public function scopeWithQueryOptions($builder, BookmarkAttributes $columns)
    {
        $builder->addSelect($this->getQualifiedKeyName());

        if ($columns->isEmpty()) {
            $builder->addSelect('bookmarks.*');
        }

        if (!$columns->isEmpty()) {
            $builder->addSelect($this->qualifyColumns($columns->except(['tags', 'source'])));
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
    protected function parseTagsRelationQuery(&$builder, BookmarkAttributes $options)
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
    protected function parseSourceRelationQuery(&$builder, BookmarkAttributes $options)
    {
        $wantsSiteRelation = $options->has('source') ?: $options->isEmpty();

        if (!$wantsSiteRelation) {
            return $builder;
        }

        return $builder->with('source');
    }

    /**
     * @param Builder $builder
     *
     * @return Builder
     */
    protected function parseHealthCheckQuery(&$builder, BookmarkAttributes $options)
    {
        $condition = $options->has('is_dead_link') ?: $options->isEmpty();

        if (!$condition) {
            return $builder;
        }

        return $builder->addSelect('bookmarks_health.is_healthy')
            ->join('bookmarks_health', 'bookmarks.id', '=', 'bookmarks_health.bookmark_id', 'left outer');
    }

    /**
     * @param Builder $builder
     *
     * @return Builder
     */
    protected function parseHasDuplicatesQuery(&$builder, BookmarkAttributes $options)
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

        return $builder->selectSub($query, 'has_duplicates');
    }
}
