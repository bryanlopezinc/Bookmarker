<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder\Roles;

use App\Http\Handlers\RequestHandlersQueue;
use Illuminate\Http\JsonResponse;
use App\Http\Handlers\Constraints;
use App\Models\Folder;
use App\Models\FolderRole;
use App\Models\FolderRolePermission;
use App\Models\Scopes\WherePublicIdScope;
use App\Models\User;
use App\ValueObjects\PublicId\FolderPublicId;
use App\ValueObjects\PublicId\RolePublicId;
use Illuminate\Http\Request;

final class DeleteRoleController
{
    public function __invoke(Request $request, string $folderId, string $roleId): JsonResponse
    {
        [$folderId, $roleId] = [
            FolderPublicId::fromRequest($folderId), RolePublicId::fromRequest($roleId)
        ];

        $query = Folder::query()
            ->tap(new WherePublicIdScope($folderId))
            ->select([
                'id',
                'roleId' => FolderRole::query()
                    ->select('id')
                    ->tap(new WherePublicIdScope($roleId))
                    ->whereColumn('folder_id', 'folders.id')
            ]);

        $requestHandlersQueue = new RequestHandlersQueue([
            new Constraints\FolderExistConstraint(),
            new Constraints\RoleExistsConstraint(),
            new Constraints\MustHaveRoleAccessConstraint(User::fromRequest($request)),
        ]);

        $requestHandlersQueue->scope($query);

        $requestHandlersQueue->handle($folder = $query->firstOrNew());

        FolderRole::query()->whereKey($folder->roleId)->delete();

        FolderRolePermission::query()->where('role_id', $folder->roleId)->delete();

        return new JsonResponse();
    }
}
