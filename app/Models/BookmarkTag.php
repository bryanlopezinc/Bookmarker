<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class BookmarkTag extends Model
{
    public const UPDATED_AT =null;

    /**
     * {@inheritdoc}
     */
    protected $table = 'bookmarks_tags';
    
    /**
     * {@inheritdoc}
     */
    protected $guarded = [];
}
