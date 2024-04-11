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
    public function __invoke(Request $request, string $folderId, string $collaboratorId): JsonResponse
    {
        $folder = Folder::query()->select('user_id')->whereKey($folderId)->firstOrNew();

        if ( ! $folder->exists) {
            throw new FolderNotFoundException();
        }

        FolderNotFoundException::throwIfDoesNotBelongToAuthUser($folder);

        $affectedRows = BannedCollaborator::query()
            ->where('user_id', $collaboratorId)
            ->where('folder_id', $folderId)
            ->delete();

        if ($affectedRows === 0) {
            throw new UserNotFoundException();
        }

        return response()->json();
    }
}
