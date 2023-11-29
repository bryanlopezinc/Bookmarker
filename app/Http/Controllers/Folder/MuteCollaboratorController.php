<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Services\Folder\MuteCollaboratorService;
use App\Services\Folder\UnMuteCollaboratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MuteCollaboratorController
{
    public function post(Request $request, MuteCollaboratorService $service): JsonResponse
    {
        $service(
            (int)$request->route('folder_id'),
            (int)$request->route('collaborator_id'),
            auth()->id()
        );

        return response()->json(status: JsonResponse::HTTP_CREATED);
    }

    public function delete(Request $request, UnMuteCollaboratorService $service): JsonResponse
    {
        $service(
            (int)$request->route('folder_id'),
            (int)$request->route('collaborator_id'),
        );

        return response()->json(status: JsonResponse::HTTP_OK);
    }
}
