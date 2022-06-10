<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Bookmark;
use App\Models\Folder as Model;
use App\Models\FolderBookmark;
use App\ValueObjects\ResourceID;
use Illuminate\Database\Eloquent\Collection;

final class DeleteFoldersRepository
{
    public function delete(ResourceID $folderID): bool
    {
        return $this->deleteFolder($folderID);
    }

    /**
     * Delete a folder and all of its contents
     */
    public function deleteRecursive(ResourceID $folderID): bool
    {
        return $this->deleteFolder($folderID, true);
    }

    private function deleteFolder(ResourceID $folderID, bool $recursive = false): bool
    {
        $deleteFn = fn () => (bool) Model::query()->whereKey($folderID->toInt())->delete();

        if (!$recursive) {
            return $deleteFn();
        }

        FolderBookmark::query()->where('folder_id', $folderID->toInt())->chunkById(100, function (Collection $chunk) {
            Bookmark::query()->whereKey($chunk->pluck('bookmark_id')->all())->delete();

            $chunk->toQuery()->delete();
        });

        return $deleteFn();
    }
}
