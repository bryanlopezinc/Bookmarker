<?php

declare(strict_types=1);

namespace App\Services;

use App\Policies\EnsureAuthorizedUserOwnsResource;
use App\Repositories\{DeleteFoldersRepository, FoldersRepository};
use App\ValueObjects\ResourceID;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;

final class DeleteFolderService
{
    public function __construct(private DeleteFoldersRepository $deleteFoldersRepository, private FoldersRepository $foldersRepository)
    {
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
        $this->validateFolder($folderID);

        if ($recursive) {
            $this->deleteFoldersRepository->deleteRecursive($folderID);
        } else {
            $this->deleteFoldersRepository->delete($folderID);
        }
    }

    private function validateFolder(ResourceID $folderID): void
    {
        $folder = $this->foldersRepository->findByID($folderID);

        if (!$folder) {
            throw new HttpResponseException(response()->json([
                'message' => "The folder does not exists"
            ], Response::HTTP_NOT_FOUND));
        }

        (new EnsureAuthorizedUserOwnsResource)($folder);
    }
}
