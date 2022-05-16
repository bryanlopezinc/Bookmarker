<?php

declare(strict_types=1);

namespace App\Models;

use App\QueryColumns\BookmarkQueryColumns as BookmarkColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property string $description
 * @property string $title
 * @property bool $has_custom_title
 * @property bool $description_set_by_user
 * @property string $url
 * @property string|null $preview_image_url
 * @property int $user_id
 * @property int $site_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property bool $is_dead_link
 * @method static Builder WithQueryOptions(BookmarkColumns $queryOptions)
 */
final class Bookmark extends Model
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
        'is_dead_link' => 'bool'
    ];

    public function tags(): HasManyThrough
    {
        return $this->hasManyThrough(Tag::class, BookmarkTag::class, 'bookmark_id', 'id', 'id', 'tag_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(WebSite::class, 'site_id', 'id');
    }

    /**
     * @param Builder $builder
     *
     * @return Builder
     */
    public function scopeWithQueryOptions($builder, BookmarkColumns $columns)
    {
        $builder->addSelect($this->getQualifiedKeyName());

        if ($columns->isEmpty()) {
            $builder->addSelect('bookmarks.*');
        }

        if (!$columns->isEmpty()) {
            $builder->addSelect($this->qualifyColumns($columns->except(['tags', 'site'])));
        }

        $this->parseTagsRelationQuery($builder, $columns);
        $this->parseSiteRelationQuery($builder, $columns);
        $this->parseHealthCheckquery($builder, $columns);

        return $builder;
    }

    /**
     * @param Builder $builder
     *
     * @return Builder
     */
    protected function parseTagsRelationQuery(&$builder, BookmarkColumns $options)
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
    protected function parseSiteRelationQuery(&$builder, BookmarkColumns $options)
    {
        $wantsSiteRelation = $options->has('site') ?: $options->isEmpty();

        if (!$wantsSiteRelation) {
            return $builder;
        }

        return $builder->with('site');
    }

    /**
     * @param Builder $builder
     *
     * @return Builder
     */
    protected function parseHealthCheckquery(&$builder, BookmarkColumns $options)
    {
        $condtion = $options->has('is_dead_link') ?: $options->isEmpty();

        if (!$condtion) {
            return $builder;
        }

        $sql = <<<SQL
                        case
                            WHEN bookmarks_health.is_healthy IS NULL THEN 0
                            ELSE bookmarks_health.is_healthy
                        END as 'is_dead_link'
                    SQL;

        return $builder->addSelect('bookmarks_health.is_healthy', DB::raw($sql))
            ->join('bookmarks_health', 'bookmarks.id', '=', 'bookmarks_health.bookmark_id', 'left outer');
    }
}
