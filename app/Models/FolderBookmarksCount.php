<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $count
 * @property int $folder_id foreign key to \App\Models\Folder
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
final class FolderBookmarksCount extends Model
{
    /**
     * {@inheritdoc}
     */
    protected $table = 'folders_bookmarks_count';

    /**
     * {@inheritdoc}
     */
    protected $guarded = [];
}
