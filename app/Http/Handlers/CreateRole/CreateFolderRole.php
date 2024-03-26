<?php

declare(strict_types=1);

namespace App\Http\Handlers\CreateRole;

use App\Contracts\FolderRequestHandlerInterface;
use App\DataTransferObjects\CreateFolderRoleData;
use App\Models\Folder;
use App\Models\FolderPermission;
use App\Models\FolderRolePermission;
use App\UAC;
use Illuminate\Support\Facades\DB;

final class CreateFolderRole implements FolderRequestHandlerInterface
{
    private readonly CreateFolderRoleData $data;

    public function __construct(CreateFolderRoleData $data)
    {
        $this->data = $data;
    }

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder): void
    {
        $permissions = UAC::fromRequest($this->data->permissions);

        $role = $folder->roles()->create(['name' => $this->data->name]);

        /** @var \Illuminate\Database\Eloquent\Builder */
        $query = FolderPermission::query()
            ->select(DB::raw($role->id), 'id')
            ->whereIn('name', $permissions->toArray());

        FolderRolePermission::insertUsing(['role_id', 'permission_id'], $query);
    }
}
