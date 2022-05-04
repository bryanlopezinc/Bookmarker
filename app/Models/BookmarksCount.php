<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class BookmarksCount extends Model
{
    /**
     * {@inheritdoc}
     */
    protected $table = 'users_bookmarks_count';

    /**
     * {@inheritdoc}
     */
    protected $guarded = [];

    /**
     * {@inheritdoc}
     */
    public $timestamps = false;
}
