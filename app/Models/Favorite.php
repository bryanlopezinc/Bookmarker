<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $bookmark_id foreign key to \App\Models\Bookmark
 * @property int $user_id foreign key to \App\Models\User
 * @property \Carbon\Carbon $created_at
 */
final class Favorite extends Model
{
    public const UPDATED_AT = null;

    /**
     * {@inheritdoc}
     */
    protected $table = 'favorites';

    /**
     * {@inheritdoc}
     */
    protected $guarded = [];
}
