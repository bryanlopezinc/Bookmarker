<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @property int                                                            $id
 * @property string                                                         $name
 * @property int                                                            $folder_id
 * @property \Carbon\Carbon                                                 $created_at
 * @property \Carbon\Carbon|null                                            $updated_at
 * @property \Illuminate\Database\Eloquent\Collection<FolderRolePermission> $permissions
 */
final class FolderRole extends Model
{
    /**
     * {@inheritdoc}
     */
    protected $table = 'folders_roles';

    /**
     * {@inheritdoc}
     */
    protected $guarded = [];

    public function permissions(): HasMany
    {
        return $this->hasMany(FolderRolePermission::class, 'role_id', 'id')->select(['role_id', 'permission_id']);
    }

    public function assignees(): HasManyThrough
    {
        return $this->hasManyThrough(
            User::class,
            FolderCollaboratorRole::class,
            'role_id',
            'id',
            'id',
            'collaborator_id'
        );
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class, 'folder_id', 'id', );
    }
}
