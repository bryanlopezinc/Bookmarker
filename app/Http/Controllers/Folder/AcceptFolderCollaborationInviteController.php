<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Services\Folder\AcceptFolderCollaborationInviteService as Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class AcceptFolderCollaborationInviteController
{
    public function __invoke(Request $request, Service $service): JsonResponse
    {
        $request->validate(['invite_hash' => ['required', 'uuid']]);

        $service->fromRequest($request);

        return response()->json(status: Response::HTTP_CREATED);
    }
}
