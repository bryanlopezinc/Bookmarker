<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder\Roles;

use App\Http\Handlers\UpdateRole\Handler;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\CreateOrUpdateRoleRequest as Request;
use App\Models\User;

final class UpdateRoleController
{
    public function __invoke(Request $request, Handler $handler, string $folderId, string $roleId): JsonResponse
    {
        $handler->handle(
            (int) $folderId,
            User::fromRequest($request),
            (int) $roleId,
            $request->validated('name')
        );

        return new JsonResponse();
    }
}
