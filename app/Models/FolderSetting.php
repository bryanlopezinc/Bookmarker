<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $key
 * @property string $value
 * @property int $folder_id
 */
final class FolderSetting extends Model
{
    /**
     * {@inheritdoc}
     */
    protected $table = 'folder_settings';

    /**
     * {@inheritdoc}
     */
    protected $guarded = [];

    /**
     * {@inheritdoc}
     */
    public $timestamps = false;
}
