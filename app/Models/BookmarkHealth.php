<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $bookmark_id unique foreign key to \App\Models\Bookmark
 * @property bool $is_healthy
 * @property \Carbon\Carbon $last_checked
 * @property \Carbon\Carbon $created_at
 */
final class BookmarkHealth extends Model
{
    public const UPDATED_AT = null;

    /**
     * {@inheritdoc}
     */
    protected $table = 'bookmarks_health';

    /**
     * {@inheritdoc}
     */
    protected $guarded = [];

    /**
     * {@inheritdoc}
     */
    protected $casts = [
        'is_healthy' => 'bool',
    ];
}
