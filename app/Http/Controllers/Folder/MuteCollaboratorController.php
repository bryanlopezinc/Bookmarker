<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Rules\ResourceIdRule;
use App\Services\Folder\MuteCollaboratorService;
use App\Services\Folder\UnMuteCollaboratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MuteCollaboratorController
{
    public function __invoke(Request $request, MuteCollaboratorService $service): JsonResponse
    {
        $request->validate([
            'folder_id'       => ['required', new ResourceIdRule()],
            'collaborator_id' => ['required', new ResourceIdRule()],
        ]);

        $service(
            $request->integer('folder_id'),
            $request->integer('collaborator_id'),
            auth()->id()
        );

        return response()->json(status: JsonResponse::HTTP_CREATED);
    }

    public function unMute(Request $request, UnMuteCollaboratorService $service): JsonResponse
    {
        $request->validate([
            'folder_id'       => ['required', new ResourceIdRule()],
            'collaborator_id' => ['required', new ResourceIdRule()],
        ]);

        $service(
            $request->integer('folder_id'),
            $request->integer('collaborator_id'),
        );

        return response()->json(status: JsonResponse::HTTP_OK);
    }
}
