<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $bookmark_id
 * @property bool $status_code
 * @property \Carbon\Carbon $last_checked
 * @property \Carbon\Carbon $created_at
 */
final class BookmarkHealth extends Model
{
    public const UPDATED_AT = 'last_checked';

    /**
     * {@inheritdoc}
     */
    protected $table = 'bookmarks_health';

    /**
     * {@inheritdoc}
     */
    protected $guarded = [];
}
