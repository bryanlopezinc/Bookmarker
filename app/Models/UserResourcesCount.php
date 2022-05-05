<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class UserResourcesCount extends Model
{
    public const BOOKMARKS_TYPE = 3;
    public const FAVOURITES_TYPE = 4;

    /**
     * {@inheritdoc}
     */
    protected $guarded = [];

    /**
     * {@inheritdoc}
     */
    protected $table = 'users_resources_counts';
}