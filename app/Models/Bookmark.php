<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\TaggableInterface;
use App\Enums\TaggableType;
use App\Observers\BookmarkObserver;
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
 * @property int $site_id foreign key to \App\Models\WebSite
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
        'is_healthy' => 'bool'
    ];

    /**
     * {@inheritdoc}
     */
    protected static function booted()
    {
        self::observe([new BookmarkObserver]);
    }

    public function tags(): HasManyThrough
    {
        return $this->hasManyThrough(Tag::class, Taggable::class, 'taggable_id', 'id', 'id', 'tag_id')
            ->where('taggable_type', Taggable::BOOKMARK_TYPE);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(WebSite::class, 'site_id', 'id');
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
    protected function parseSiteRelationQuery(&$builder, BookmarkAttributes $options)
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
    protected function parseHealthCheckquery(&$builder, BookmarkAttributes $options)
    {
        $condtion = $options->has('is_dead_link') ?: $options->isEmpty();

        if (!$condtion) {
            return $builder;
        }

        return $builder->addSelect('bookmarks_health.is_healthy')
            ->join('bookmarks_health', 'bookmarks.id', '=', 'bookmarks_health.bookmark_id', 'left outer');
    }
}
