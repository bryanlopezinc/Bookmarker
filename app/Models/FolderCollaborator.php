<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $folder_id
 * @property int $collaborator_id
 * @property int $invited_by
 * @property \Carbon\Carbon $joined_at
 * @property \Illuminate\Database\Eloquent\Collection<FolderCollaboratorRole> $roles
 */
final class FolderCollaborator extends Model
{
    public const UPDATED_AT = null;
    public const CREATED_AT = 'joined_at';

    /**
     * {@inheritdoc}
     */
    protected $table = 'folders_collaborators';

    /**
     * {@inheritdoc}
     */
    protected $guarded = [];

    public function roles(): HasMany
    {
        return $this->hasMany(FolderCollaboratorRole::class, 'collaborator_id', 'collaborator_id');
    }
}
