<?php

declare(strict_types=1);

namespace App\Http\Handlers\UpdateRole;

use App\Models\Folder;
use App\Http\Handlers\Constraints;
use App\Contracts\FolderRequestHandlerInterface as HandlerInterface;
use App\Http\Handlers\RequestHandlersQueue;
use App\Models\User;

final class Handler
{
    public function handle(int $folderId, User $user, int $roleId, string $role): void
    {
        $requestHandlersQueue = new RequestHandlersQueue($this->getConfiguredHandlers($user, $roleId, $role));

        $query = Folder::query()->select(['id'])->whereKey($folderId);

        $requestHandlersQueue->scope($query);

        $folder = $query->firstOrNew();

        $requestHandlersQueue->handle(function (HandlerInterface $handler) use ($folder) {
            $handler->handle($folder);
        });
    }

    private function getConfiguredHandlers(User $user, int $roleId, string $role): array
    {
        return [
            new Constraints\FolderExistConstraint(),
            new Constraints\CanCreateOrModifyRoleConstraint($user),
            new Constraints\RoleExistsConstraint($roleId),
            new Constraints\UniqueRoleNameConstraint($role),
            new UpdateFolderRole($role)
        ];
    }
}
