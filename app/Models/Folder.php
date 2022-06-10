<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * @property int $id
 * @property string|null $description
 * @property string $name
 * @property int $user_id foreign key to \App\Models\User
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @method static Builder WithBookmarksCount()
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
     * @param \Illuminate\Database\Eloquent\Builder $builder
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithBookmarksCount($builder)
    {
        return $builder
            ->select('folders.*')
            ->addSelect('fbc.count as bookmarks_count')
            ->leftJoin('folders_bookmarks_count as fbc', 'folders.id', '=', 'fbc.folder_id');
    }
}
