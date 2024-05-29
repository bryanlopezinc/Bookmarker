<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Http\Handlers\AcceptInvite\HandlerInterface;
use App\ValueObjects\InviteId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class AcceptFolderCollaborationInviteController
{
    public function __invoke(Request $request, HandlerInterface $handler): JsonResponse
    {
        $inviteId = $request->validate(['invite_hash' => ['required', 'uuid']])['invite_hash'];

        $handler->handle(new InviteId($inviteId));

        return new JsonResponse(status: Response::HTTP_CREATED);
    }
}
