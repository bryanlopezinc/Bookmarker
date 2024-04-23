<?php

declare(strict_types=1);

namespace App\Models;

use App\UAC;
use App\ValueObjects\PublicId\RolePublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @property int                                                        $id
 * @property RolePublicId                                               $public_id
 * @property string                                                     $name
 * @property int                                                        $folder_id
 * @property \Carbon\Carbon                                             $created_at
 * @property \Carbon\Carbon|null                                        $updated_at
 * @property \Illuminate\Database\Eloquent\Collection<FolderPermission> $permissions
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

    /**
     * {@inheritdoc}
     */
    protected $casts = [
        'public_id'  => RolePublicId::class
    ];

    public function permissions(): HasManyThrough
    {
        return $this->hasManyThrough(
            FolderPermission::class,
            FolderRolePermission::class,
            'role_id',
            'id',
            'id',
            'permission_id'
        );
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

    public function accessControls(): UAC
    {
        return new UAC($this->permissions->all());
    }
}
