<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property \Carbon\Carbon $created_at
 */
final class FolderPermission extends Model
{
    public const UPDATED_AT = null;


    /**
     * {@inheritdoc}
     */
    protected $table = 'folders_permissions';

    /**
     * {@inheritdoc}
     */
    protected $guarded = [];
}
