<?php

declare(strict_types=1);

namespace App\Http\Handlers\CreateRolePermission;

use App\Models\Folder;
use App\Http\Handlers\Constraints;
use App\Contracts\FolderRequestHandlerInterface as HandlerInterface;
use App\DataTransferObjects\AddPermissionToRoleData;
use App\Http\Handlers\RequestHandlersQueue;

final class Handler
{
    public function handle(AddPermissionToRoleData $data): void
    {
        $requestHandlersQueue = new RequestHandlersQueue($this->getConfiguredHandlers($data));

        $query = Folder::query()->select(['id'])->whereKey($data->folderId);

        $requestHandlersQueue->scope($query);

        $folder = $query->firstOrNew();

        $requestHandlersQueue->handle(function (HandlerInterface $handler) use ($folder) {
            $handler->handle($folder);
        });
    }

    private function getConfiguredHandlers(AddPermissionToRoleData $data): array
    {
        return [
            new Constraints\FolderExistConstraint(),
            new Constraints\RoleExistsConstraint($data->roleId),
            new Constraints\CanCreateOrModifyRoleConstraint($data->authUser),
            new UniqueRoleConstraint($data->roleId, $data->permission),
            new Constraints\UniqueRolePermissions($data->permission, $data->roleId),
            new CreateRolePermission($data->roleId, $data->permission)
        ];
    }
}
