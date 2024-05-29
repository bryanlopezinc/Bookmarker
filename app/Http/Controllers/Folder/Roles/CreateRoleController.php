<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder\Roles;

use App\DataTransferObjects\CreateFolderRoleData;
use App\Http\Handlers\CreateRole\Handler;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\CreateOrUpdateRoleRequest as Request;
use App\ValueObjects\PublicId\FolderPublicId;

final class CreateRoleController
{
    public function __invoke(Request $request, Handler $handler, string $folderId): JsonResponse
    {
        $handler->handle(FolderPublicId::fromRequest($folderId), CreateFolderRoleData::fromRequest($request));

        return new JsonResponse(status: JsonResponse::HTTP_CREATED);
    }
}
