<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Http\Handlers\SendInvite\Handler;
use App\Http\Requests\SendFolderCollaborationInviteRequest as Request;
use Illuminate\Http\JsonResponse;

final class SendFolderCollaborationInviteController
{
    public function __invoke(Request $request, Handler $requestHandler): JsonResponse
    {
        $requestHandler->handle($request->input('email'), $request->integer('folder_id'));

        return new JsonResponse();
    }
}
