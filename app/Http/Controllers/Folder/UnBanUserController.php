<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Exceptions\FolderNotFoundException;
use App\Exceptions\UserNotFoundException;
use App\Models\BannedCollaborator;
use App\Models\Folder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class UnBanUserController
{
    public function __invoke(Request $request): JsonResponse
    {
        $folder = Folder::query()->find($request->route('folder_id'), ['user_id']);

        FolderNotFoundException::throwIf(!$folder);

        FolderNotFoundException::throwIfDoesNotBelongToAuthUser($folder);

        $affectedRows = BannedCollaborator::query()
            ->where('user_id', $request->route('collaborator_id'))
            ->where('folder_id', $request->route('folder_id'))
            ->delete();

        if ($affectedRows === 0) {
            throw new UserNotFoundException();
        }

        return response()->json();
    }
}
