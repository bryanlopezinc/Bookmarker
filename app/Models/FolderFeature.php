<?php

declare(strict_types=1);

namespace App\Models;

use Exception;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int            $id
 * @property string         $name
 * @property \Carbon\Carbon $created_at
 */
final class FolderFeature extends Model
{
    public const UPDATED_AT = null;

    /**
     * {@inheritdoc}
     */
    protected $table = 'folders_features_types';

    /**
     * {@inheritdoc}
     */
    protected $guarded = [];

    public static function booted(): void
    {
        self::deleting(function (self $model) {
            throw new Exception('Deleting of feature type is not allowed.');
        });
    }
}
