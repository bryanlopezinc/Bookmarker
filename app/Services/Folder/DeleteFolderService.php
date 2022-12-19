<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Contracts\FolderRepositoryInterface;
use App\Events\FolderModifiedEvent;
use App\Policies\EnsureAuthorizedUserOwnsResource;
use App\Repositories\Folder\DeleteFolderRepository;
use App\ValueObjects\ResourceID;
use App\QueryColumns\FolderAttributes as Attributes;

final class DeleteFolderService
{
    public function __construct(
        private DeleteFolderRepository $deleteFolderRepository,
        private FolderRepositoryInterface $folderRepository
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
        $folder = $this->folderRepository->find($folderID, Attributes::only('id,user_id'));

        (new EnsureAuthorizedUserOwnsResource())($folder);

        if ($recursive) {
            $this->deleteFolderRepository->deleteRecursive($folder);
        } else {
            $this->deleteFolderRepository->delete($folder);
        }

        event(new FolderModifiedEvent($folderID));
    }
}
