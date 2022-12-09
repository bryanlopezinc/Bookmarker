<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $folder_id
 * @property int $user_id
 * @property int $permission_id
 * @property \Carbon\Carbon $created_at
 */
final class FolderCollaboratorPermission extends Model
{
    public const UPDATED_AT = null;

    /**
     * {@inheritdoc}
     */
    protected $table = 'folders_collaborators_permissions';

    /**
     * {@inheritdoc}
     */
    protected $guarded = [];
}
