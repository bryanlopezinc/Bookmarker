<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder\Roles;

use App\Http\Handlers\RequestHandlersQueue;
use Illuminate\Http\JsonResponse;
use App\Http\Handlers\Constraints;
use App\Models\Folder;
use App\Contracts\FolderRequestHandlerInterface as HandlerInterface;
use App\Models\FolderRole;
use App\Models\FolderRolePermission;
use App\Models\User;
use Illuminate\Http\Request;

final class DeleteRoleController
{
    public function __invoke(Request $request, string $folderId, string $roleId): JsonResponse
    {
        $query = Folder::query()->select(['id'])->whereKey($folderId);

        $requestHandlersQueue = new RequestHandlersQueue([
            new Constraints\FolderExistConstraint(),
            new Constraints\RoleExistsConstraint((int) $roleId),
            new Constraints\CanCreateOrModifyRoleConstraint(User::fromRequest($request)),
        ]);

        $requestHandlersQueue->scope($query);

        $folder = $query->firstOrNew();

        $requestHandlersQueue->handle(function (HandlerInterface $handler) use ($folder) {
            $handler->handle($folder);
        });

        FolderRole::query()->whereKey($roleId)->delete();
        FolderRolePermission::query()->where('role_id', $roleId)->delete();

        return new JsonResponse();
    }
}
