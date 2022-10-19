<?php

declare(strict_types=1);

namespace App\Repositories\Folder;

use App\Contracts\FolderRepositoryInterface;
use App\ValueObjects\ResourceID;
use App\QueryColumns\FolderAttributes;
use App\DataTransferObjects\Folder;
use App\Exceptions\FolderNotFoundHttpResponseException;
use App\Models\DeletedUser;

/**
 * All user folders are not deleted immediately when user deletes account but are deleted by
 * background tasks. This class exists to ensure actions won't be performed on folders that
 * belongs to a deleted user
 */
final class CheckFolderBelongsToDeletedUser implements FolderRepositoryInterface
{
    public function __construct(private FolderRepositoryInterface $repository)
    {
    }

    public function find(ResourceID $folderID, ?FolderAttributes $attributes = null): Folder
    {
        $folder = $this->repository->find($folderID, $attributes);

        $folderBelongsToDeletedUser = DeletedUser::query()->where('user_id', $folder->ownerID->value())->exists();

        if ($folderBelongsToDeletedUser) {
            throw new FolderNotFoundHttpResponseException;
        }

        return $folder;
    }
}
