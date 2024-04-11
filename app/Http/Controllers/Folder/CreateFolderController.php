<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\DataTransferObjects\CreateFolderData;
use App\Http\Handlers\CreateFolder\HandlerInterface;
use App\Http\Requests\CreateOrUpdateFolderRequest as Request;
use Illuminate\Http\JsonResponse;

final class CreateFolderController
{
    public function __invoke(Request $request, HandlerInterface $service): JsonResponse
    {
        $service->create(CreateFolderData::fromRequest($request));

        return new JsonResponse(status: JsonResponse::HTTP_CREATED);
    }
}
