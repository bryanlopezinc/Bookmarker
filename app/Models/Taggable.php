<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $tag_id
 * @property int $taggable_id
 * @property int $taggable_type
 */
final class Taggable extends Model
{
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
