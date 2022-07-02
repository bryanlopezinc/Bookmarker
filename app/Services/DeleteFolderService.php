<?php

declare(strict_types=1);

namespace App\Services;

use App\Policies\EnsureAuthorizedUserOwnsResource;
use App\Repositories\{DeleteFoldersRepository, FoldersRepository};
use App\ValueObjects\ResourceID;
use App\QueryColumns\FolderAttributes as Attributes;

final class DeleteFolderService
{
    public function __construct(
        private DeleteFoldersRepository $deleteFoldersRepository,
        private FoldersRepository $foldersRepository
    ) {
    }

    public function delete(ResourceID $folderID): void
    {
        $this->deleteFolder($folderID);
    }

    /**
     * Delete a folder and all of its bookmarks
     */
    public function deleteRecursive(ResourceID $folderID): void
    {
        $this->deleteFolder($folderID, true);
    }

    private function deleteFolder(ResourceID $folderID, bool $recursive = false): void
    {
        $folder = $this->foldersRepository->find($folderID, Attributes::only('id,userId'));

        (new EnsureAuthorizedUserOwnsResource)($folder);

        if ($recursive) {
            $this->deleteFoldersRepository->deleteRecursive($folderID);
        } else {
            $this->deleteFoldersRepository->delete($folderID);
        }
    }
}
