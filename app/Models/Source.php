<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $host
 * @property string $name
 * @property \Carbon\Carbon $name_updated_at
 * @property \Carbon\Carbon $created_at
 */
final class Source extends Model
{
    public const UPDATED_AT = null;

    /**
     * {@inheritdoc}
     */
    protected $table = 'bookmarks_sources';

    /**
     * {@inheritdoc}
     */
    protected $guarded = [];
}
