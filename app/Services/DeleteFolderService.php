<?php

declare(strict_types=1);

namespace App\Services;

use App\Policies\EnsureAuthorizedUserOwnsResource;
use App\Repositories\{DeleteFoldersRepository, FoldersRepository};
use App\ValueObjects\ResourceID;
use App\Exceptions\FolderNotFoundHttpResponseException as HttpException;

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
        (new EnsureAuthorizedUserOwnsResource)($this->foldersRepository->find($folderID));

        if ($recursive) {
            $this->deleteFoldersRepository->deleteRecursive($folderID);
        } else {
            $this->deleteFoldersRepository->delete($folderID);
        }
    }
}
