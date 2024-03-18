<?php

declare(strict_types=1);

namespace App\Http\Handlers\RemoveRolePermission;

use App\Models\Folder;
use App\Http\Handlers\Constraints;
use App\Contracts\FolderRequestHandlerInterface as HandlerInterface;
use App\Http\Handlers\RequestHandlersQueue;
use App\Models\User;

final class Handler
{
    public function handle(int $folderId, int $roleId, string $permission, User $user): void
    {
        $requestHandlersQueue = new RequestHandlersQueue($this->getConfiguredHandlers($roleId, $permission, $user));

        $query = Folder::query()->select(['id'])->whereKey($folderId);

        $requestHandlersQueue->scope($query);

        $folder = $query->firstOrNew();

        $requestHandlersQueue->handle(function (HandlerInterface $handler) use ($folder) {
            $handler->handle($folder);
        });
    }

    private function getConfiguredHandlers(int $roleId, string $permission, User $user): array
    {
        return [
            new Constraints\FolderExistConstraint(),
            new Constraints\RoleExistsConstraint($roleId),
            new Constraints\CanCreateOrModifyRoleConstraint($user),
            new CannotRemoveAllRolePermissionsConstraint($roleId),
            new DeleteRolePermission($roleId, $permission)
        ];
    }
}
