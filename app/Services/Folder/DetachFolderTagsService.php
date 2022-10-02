<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Collections\TagsCollection;
use App\Contracts\FolderRepositoryInterface;
use App\Events\FolderModifiedEvent;
use App\Events\TagsDetachedEvent;
use App\Policies\EnsureAuthorizedUserOwnsResource;
use App\QueryColumns\FolderAttributes;
use App\ValueObjects\ResourceID;
use App\Repositories\TagRepository;
use App\ValueObjects\UserID;

final class DetachFolderTagsService
{
    public function __construct(
        private FolderRepositoryInterface $repository,
        private TagRepository $tagsRepository
    ) {
    }

    public function delete(ResourceID $folderID, TagsCollection $tagsCollection): void
    {
        $folder = $this->repository->find($folderID, FolderAttributes::only('user_id,id,tags'));

        (new EnsureAuthorizedUserOwnsResource)($folder);

        if (!$folder->tags->contains($tagsCollection)) {
            return;
        }

        $this->tagsRepository->detach($tagsCollection, $folderID);

        event(new FolderModifiedEvent($folderID));

        event(new TagsDetachedEvent($folder->tags, UserID::fromAuthUser()));
    }
}
