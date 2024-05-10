<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\DataTransferObjects\UpdateFolderRequestData;
use App\Http\Handlers\UpdateFolder\Handler;
use App\Http\Requests\CreateOrUpdateFolderRequest as Request;
use App\ValueObjects\PublicId\FolderPublicId;
use Illuminate\Http\JsonResponse;

final class UpdateFolderController
{
    public function __invoke(Request $request, Handler $requestHandler, string $folderId): JsonResponse
    {
        $requestHandler->handle(FolderPublicId::fromRequest($folderId), UpdateFolderRequestData::fromRequest($request));

        return new JsonResponse();
    }
}
