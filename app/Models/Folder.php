<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\TaggableInterface;
use App\Enums\TaggableType;
use App\QueryColumns\FolderAttributes;
use App\ValueObjects\ResourceID;
use App\ValueObjects\UserID;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @property int $id
 * @property string|null $description
 * @property string $name
 * @property array $settings
 * @property bool $is_public
 * @property int $user_id foreign key to \App\Models\User
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @method static Builder onlyAttributes(FolderAttributes $attributes)
 */
final class Folder extends Model implements TaggableInterface
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
        'is_public' => 'bool',
        'settings' => 'array'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function taggableID(): ResourceID
    {
        return new ResourceID($this->id);
    }

    public function taggableType(): TaggableType
    {
        return TaggableType::FOLDER;
    }

    public function taggedBy(): UserID
    {
        return new UserID($this->user_id);
    }

    public function tags(): HasManyThrough
    {
        return $this->hasManyThrough(Tag::class, Taggable::class, 'taggable_id', 'id', 'id', 'tag_id')
            ->where('taggable_type', Taggable::FOLDER_TYPE);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $builder
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOnlyAttributes($builder, FolderAttributes $attributes)
    {
        if ($attributes->isEmpty()) {
            $builder->addSelect('folders.*');
        }

        if (!$attributes->isEmpty()) {
            $builder->addSelect($this->qualifyColumns($attributes->except(['bookmarks_count', 'tags'])));
        }

        $this->parseBookmarksCountRelationQuery($builder, $attributes);
        $this->parseTagsRelationQuery($builder, $attributes);

        return $builder;
    }

    /**
     * @param Builder $builder
     *
     * @return Builder
     */
    protected function parseBookmarksCountRelationQuery(&$builder, FolderAttributes $attributes)
    {
        $wantsBookmarksCount = $attributes->has('bookmarks_count') ?: $attributes->isEmpty();

        if (!$wantsBookmarksCount) {
            return $builder;
        }

        return $builder->addSelect('fbc.count as bookmarks_count')
            ->leftJoin('folders_bookmarks_count as fbc', 'folders.id', '=', 'fbc.folder_id');
    }

    /**
     * @param Builder $builder
     *
     * @return Builder
     */
    protected function parseTagsRelationQuery(&$builder, FolderAttributes $attributes)
    {
        $wantsTags = $attributes->has('tags') ?: $attributes->isEmpty();

        if (!$wantsTags) {
            return $builder;
        }

        return $builder->with('tags');
    }
}
