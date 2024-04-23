<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Exceptions\FolderNotFoundException;
use App\Exceptions\UserNotFoundException;
use App\Models\BannedCollaborator;
use App\Models\Folder;
use App\Models\Scopes\WherePublicIdScope;
use App\Models\User;
use App\ValueObjects\PublicId\FolderPublicId;
use App\ValueObjects\PublicId\UserPublicId;
use Illuminate\Http\JsonResponse;

final class UnBanUserController
{
    public function __invoke(string $folderId, string $collaboratorId): JsonResponse
    {
        $folder = Folder::query()
            ->select([
                'user_id',
                'id',
                'collaboratorId' => User::select(['id'])->tap(new WherePublicIdScope(UserPublicId::fromRequest($collaboratorId)))
            ])
            ->tap(new WherePublicIdScope(FolderPublicId::fromRequest($folderId)))
            ->firstOrNew();

        if ( ! $folder->exists) {
            throw new FolderNotFoundException();
        }

        FolderNotFoundException::throwIfDoesNotBelongToAuthUser($folder);

        $affectedRows = BannedCollaborator::query()
            ->where('user_id', $folder->collaboratorId)
            ->where('folder_id', $folder->id)
            ->delete();

        if ($affectedRows === 0) {
            throw new UserNotFoundException();
        }

        return response()->json();
    }
}
