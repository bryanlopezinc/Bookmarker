<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Exceptions\FolderNotFoundException;
use App\Models\Bookmark;
use App\Models\Folder;
use App\Models\FolderBookmark;
use Illuminate\Database\Eloquent\Collection;

final class DeleteFolderService
{
    public function delete(int $folderID): void
    {
        $this->deleteFolder($folderID);
    }

    /**
     * Delete a folder and all of its bookmarks
     */
    public function deleteRecursive(int $folderID): void
    {
        $this->deleteFolder($folderID, true);
    }

    private function deleteFolder(int $folderID, bool $recursive = false): void
    {
        $folder = Folder::query()->find($folderID, ['id', 'user_id']);

        if (is_null($folder)) {
            throw new FolderNotFoundException();
        }

        FolderNotFoundException::throwIfDoesNotBelongToAuthUser($folder);

        $this->performDelete($folder, $recursive);
    }

    private function performDelete(Folder $folder, bool $deleteBookmarks = false): bool
    {
        FolderBookmark::query()
            ->where('folder_id', $folder->id)
            ->chunkById(100, function (Collection $chunk) use ($deleteBookmarks, $folder) {
                if ($deleteBookmarks) {
                    Bookmark::where('user_id', $folder->user_id)
                        ->whereIn('id', $chunk->pluck('bookmark_id'))
                        ->delete();
                }

                $chunk->toQuery()->delete();
            });

        return (bool) Folder::query()->whereKey($folder->id)->delete();
    }
}
