<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\DataTransferObjects\SendInviteRequestData;
use App\Http\Handlers\SendInvite\Handler;
use App\Http\Requests\SendFolderCollaborationInviteRequest as Request;
use Illuminate\Http\JsonResponse;

final class SendFolderCollaborationInviteController
{
    public function __invoke(Request $request, Handler $requestHandler, string $folderId): JsonResponse
    {
        $requestHandler->handle((int) $folderId, SendInviteRequestData::fromRequest($request));

        return new JsonResponse();
    }
}
