<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $collaborator_id
 * @property int $role_id
 */
final class FolderCollaboratorRole extends Model
{
    /**
     * {@inheritdoc}
     */
    protected $table = 'folders_collaborators_roles';

    /**
     * {@inheritdoc}
     */
    protected $guarded = [];
}
