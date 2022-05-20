<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The has-many-through relationship table for bookmark tags.
 * 
 * @property int $id primary key
 * @property int $bookmark_id foreign key to \App\Models\Bookmark
 * @property int $tag_id foreign key to \App\Models\Tag
 * @property \Carbon\Carbon $created_at
 */
final class BookmarkTag extends Model
{
    public const UPDATED_AT = null;

    /**
     * {@inheritdoc}
     */
    protected $table = 'bookmarks_tags';

    /**
     * {@inheritdoc}
     */
    protected $guarded = [];
}
