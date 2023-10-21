<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Exceptions\FolderNotFoundException;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class FetchFolderService
{
    /**
     * @throws FolderNotFoundException
     */
    public function find(int $id, array $attributes = []): Folder
    {
        try {
            return Folder::onlyAttributes($attributes)
                ->whereKey($id)

                // All user folders are not deleted immediately when user deletes account but are deleted by
                // background tasks. This statement exists to ensure actions won't be performed on folders that
                // belongs to a deleted user account
                ->whereExists(function (&$query) {
                    $query = User::select('id')->whereRaw('id = folders.user_id')->getQuery();
                })
                ->sole();
        } catch (ModelNotFoundException) {
            throw new FolderNotFoundException();
        }
    }
}
