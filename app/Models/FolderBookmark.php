<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The has-many-through relationship table for folder bookmarks.
 *
 * @property int $id
 * @property int $bookmark_id foreign key to \App\Models\Bookmark
 * @property int $folder_id foreign key to \App\Models\Folder
 * @property \Carbon\Carbon $created_at
 */
final class FolderBookmark extends Model
{
    const UPDATED_AT = null;
    
    /**
     * {@inheritdoc}
     */
    protected $table = 'folders_bookmarks';

    /**
     * {@inheritdoc}
     */
    protected $guarded = [];
}
