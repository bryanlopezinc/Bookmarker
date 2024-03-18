<?php

declare(strict_types=1);

namespace App\Http\Handlers\CreateRole;

use App\Contracts\FolderRequestHandlerInterface;
use App\DataTransferObjects\CreateFolderRoleData;
use App\Models\Folder;
use App\Models\FolderPermission;
use App\Models\FolderRolePermission;
use App\Repositories\Folder\PermissionRepository;
use App\UAC;
use Illuminate\Support\Collection;

final class CreateFolderRole implements FolderRequestHandlerInterface
{
    private readonly CreateFolderRoleData $data;
    private readonly PermissionRepository $permissionsRepository;

    public function __construct(CreateFolderRoleData $data, PermissionRepository $permissionsRepository = null)
    {
        $this->data = $data;
        $this->permissionsRepository = $permissionsRepository ??= new PermissionRepository();
    }

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder): void
    {
        $permissions = UAC::fromRequest($this->data->permissions);

        $role = $folder->roles()->create(['name' => $this->data->name]);

        $this->permissionsRepository
            ->findManyByName($permissions->toArray())
            ->map(fn (FolderPermission $model) => [
                'role_id'       => $role->id,
                'permission_id' => $model->id
            ])
            ->tap(fn (Collection $collection) => FolderRolePermission::insert($collection->all()));
    }
}
