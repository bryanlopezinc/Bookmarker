<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Http\Requests\SendFolderCollaborationInviteRequest as Request;
use App\Services\Folder\SendFolderCollaborationInviteService as Service;
use Illuminate\Http\JsonResponse;

final class SendFolderCollaborationInviteController
{
    public function __invoke(Request $request, Service $service): JsonResponse
    {
        $service->fromRequest($request);

        return response()->json();
    }
}
