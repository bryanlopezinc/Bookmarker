<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 *  Record of user favourites count and bookmarks count
 *
 * @property int $id
 * @property int $user_id unique foreign key \App\Models\User
 * @property int $count
 * @property int $type
 */
final class UserResourcesCount extends Model
{
    public const BOOKMARKS_TYPE = 3;
    public const FAVOURITES_TYPE = 4;
    public const FOLDERS_TYPE = 5;

    /**
     * {@inheritdoc}
     */
    protected $guarded = [];

    /**
     * {@inheritdoc}
     */
    protected $table = 'users_resources_counts';
}
