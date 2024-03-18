<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $role_id
 * @property int $permission_id
 */
final class FolderRolePermission extends Model
{
    /**
     * {@inheritdoc}
     */
    protected $table = 'folders_roles_permissions';

    /**
     * {@inheritdoc}
     */
    protected $guarded = [];
}
