<?php

declare(strict_types=1);

namespace App\Repositories\Folder;

use App\DataTransferObjects\Folder;
use App\Models\Bookmark;
use App\Models\Folder as Model;
use App\Models\FolderBookmark;
use Illuminate\Database\Eloquent\Collection;

final class DeleteFolderRepository
{
    public function delete(Folder $folder): bool
    {
        return $this->deleteFolder($folder);
    }

    /**
     * Delete a folder and all of its contents
     */
    public function deleteRecursive(Folder $folder): bool
    {
        return $this->deleteFolder($folder, true);
    }

    private function deleteFolder(Folder $folder, bool $shouldDeleteBookmarksInFolder = false): bool
    {
        FolderBookmark::query()
            ->where('folder_id', $folder->folderID->value())
            ->chunkById(100, function (Collection $chunk) use ($shouldDeleteBookmarksInFolder, $folder) {
                if ($shouldDeleteBookmarksInFolder) {
                    Bookmark::where('user_id', $folder->ownerID->value())
                        ->whereIn('id', $chunk->pluck('bookmark_id'))
                        ->delete();
                }

                $chunk->toQuery()->delete();
            });

        return (bool) Model::query()->whereKey($folder->folderID->value())->delete();
    }
}
