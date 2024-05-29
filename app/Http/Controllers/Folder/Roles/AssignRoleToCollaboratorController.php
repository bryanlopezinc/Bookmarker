<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder\Roles;

use App\Collections\RolesPublicIdsCollection;
use App\Http\Handlers\AssignRole\Handler;
use App\Models\User;
use App\Rules\PublicId\RolePublicIdRule;
use Illuminate\Http\JsonResponse;
use App\ValueObjects\PublicId\FolderPublicId;
use App\ValueObjects\PublicId\UserPublicId;
use Illuminate\Http\Request;

final class AssignRoleToCollaboratorController
{
    public function __invoke(Request $request, Handler $handler, string $folderId, string $collaboratorId): JsonResponse
    {
        $maxRoles = setting('MAX_ASSIGN_ROLES_PER_REQUEST');

        [$folderId, $collaboratorId, $roleIds] = [
            FolderPublicId::fromRequest($folderId),
            UserPublicId::fromRequest($collaboratorId),
            RolesPublicIdsCollection::fromRequest($request->input('roles'))
        ];

        $request->validate([
            'roles'   => ['required', 'array', "max:{$maxRoles}", 'filled'],
            'roles.*' => [new RolePublicIdRule(), 'distinct:strict']
        ]);

        $handler->handle($folderId, $collaboratorId, $roleIds, User::fromRequest($request));

        return new JsonResponse(status: JsonResponse::HTTP_CREATED);
    }
}
