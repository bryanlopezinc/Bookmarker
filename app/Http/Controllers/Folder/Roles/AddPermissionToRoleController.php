<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder\Roles;

use App\DataTransferObjects\AddPermissionToRoleData;
use App\Http\Handlers\CreateRolePermission\Handler;
use App\Models\User;
use App\UAC;
use App\ValueObjects\PublicId\FolderPublicId;
use App\ValueObjects\PublicId\RolePublicId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class AddPermissionToRoleController
{
    public function __invoke(Request $request, Handler $handler, string $folderId, string $roleId): JsonResponse
    {
        [$folderId, $roleId] = [
            FolderPublicId::fromRequest($folderId), RolePublicId::fromRequest($roleId)
        ];

        $request->validate([
            'permission' => ['required', 'string', Rule::in(UAC::validExternalIdentifiers())]
        ]);

        $handler->handle(
            new AddPermissionToRoleData(
                $folderId,
                $roleId,
                UAC::fromRequest($request->input('permission'))->toCollection()->sole(),
                User::fromRequest($request)
            )
        );

        return new JsonResponse(status: JsonResponse::HTTP_CREATED);
    }
}
