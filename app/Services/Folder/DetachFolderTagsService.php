<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Collections\TagsCollection;
use App\Policies\EnsureAuthorizedUserOwnsResource;
use App\QueryColumns\FolderAttributes;
use App\ValueObjects\ResourceID;
use App\Repositories\Folder\FoldersRepository;
use App\Repositories\TagsRepository;

final class DetachFolderTagsService
{
    public function __construct(
        private FoldersRepository $repository,
        private TagsRepository $tagsRepository
    ) {
    }

    public function delete(ResourceID $folderID, TagsCollection $tagsCollection): void
    {
        $folder = $this->repository->find($folderID, FolderAttributes::only('userId,id,tags'));

        (new EnsureAuthorizedUserOwnsResource)($folder);

        if (!$folder->tags->contains($tagsCollection)) {
            return;
        }

        $this->tagsRepository->detach($tagsCollection, $folderID);
    }
}
