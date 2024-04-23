<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder\Roles;

use App\Http\Handlers\UpdateRole\Handler;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\CreateOrUpdateRoleRequest as Request;
use App\Models\User;
use App\ValueObjects\PublicId\FolderPublicId;
use App\ValueObjects\PublicId\RolePublicId;

final class UpdateRoleController
{
    public function __invoke(Request $request, Handler $handler, string $folderId, string $roleId): JsonResponse
    {
        [$folderId, $roleId] = [
            FolderPublicId::fromRequest($folderId), RolePublicId::fromRequest($roleId)
        ];

        $handler->handle(
            $folderId,
            User::fromRequest($request),
            $roleId,
            $request->validated('name')
        );

        return new JsonResponse();
    }
}
