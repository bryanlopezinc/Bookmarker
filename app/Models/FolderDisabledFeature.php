<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $folder_id
 * @property int $feature_id
 */
final class FolderDisabledFeature extends Model
{
    /**
     * {@inheritdoc}
     */
    protected $table = 'folders_disabled_features';

    /**
     * {@inheritdoc}
     */
    protected $guarded = [];

    /**
     * {@inheritdoc}
     */
    public $timestamps = false;
}
