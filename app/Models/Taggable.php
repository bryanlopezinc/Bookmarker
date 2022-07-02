<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $tag_id
 * @property int $taggable_id
 * @property int $taggable_type
 * @property int $tagged_by_id
 */
final class Taggable extends Model
{
    public const BOOKMARK_TYPE = 4;
    public const FOLDER_TYPE = 5;

    /**
     * {@inheritdoc}
     */
    protected $table = 'taggables';

    /**
     * {@inheritdoc}
     */
    public $timestamps = false;

    /**
     * {@inheritdoc}
     */
    protected $guarded = [];
}
