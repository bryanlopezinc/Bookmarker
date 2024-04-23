<?php

declare(strict_types=1);

namespace App\Http\Handlers\CreateRolePermission;

use App\Models\Folder;
use App\Http\Handlers\Constraints;
use App\DataTransferObjects\AddPermissionToRoleData;
use App\Http\Handlers\RequestHandlersQueue;
use App\Models\FolderRole;
use App\Models\Scopes\WherePublicIdScope;

final class Handler
{
    public function handle(AddPermissionToRoleData $data): void
    {
        $requestHandlersQueue = new RequestHandlersQueue($this->getConfiguredHandlers($data));

        $query = Folder::query()
            ->tap(new WherePublicIdScope($data->folderId))
            ->select([
                'id',
                'roleId' => FolderRole::query()
                    ->select('id')
                    ->tap(new WherePublicIdScope($data->roleId))
                    ->whereColumn('folder_id', 'folders.id')
            ]);

        $requestHandlersQueue->scope($query);

        $requestHandlersQueue->handle($query->firstOrNew());
    }

    private function getConfiguredHandlers(AddPermissionToRoleData $data): array
    {
        return [
            new Constraints\FolderExistConstraint(),
            new Constraints\RoleExistsConstraint(),
            new Constraints\CanCreateOrModifyRoleConstraint($data->authUser),
            new UniqueRoleConstraint($data->permission),
            new Constraints\UniqueRolePermissions($data->permission),
            new CreateRolePermission($data->permission)
        ];
    }
}
