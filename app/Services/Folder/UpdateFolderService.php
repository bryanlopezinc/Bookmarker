<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Collections\TagsCollection;
use App\Contracts\FolderRepositoryInterface;
use App\DataTransferObjects\Builders\FolderBuilder;
use App\DataTransferObjects\Folder;
use App\Events\FolderModifiedEvent;
use App\Exceptions\HttpException;
use App\Http\Requests\CreateFolderRequest;
use App\Policies\EnsureAuthorizedUserOwnsResource;
use App\ValueObjects\ResourceID;
use App\QueryColumns\FolderAttributes as Attributes;
use App\Repositories\Folder\FolderRepository;
use Illuminate\Http\Response;

final class UpdateFolderService
{
    public function __construct(
        private FolderRepositoryInterface $folderRepository,
        private FolderRepository $updateFolderRepository
    ) {
    }

    public function fromRequest(CreateFolderRequest $request): void
    {
        $folder = $this->folderRepository->find(
            ResourceID::fromRequest($request, 'folder'),
            Attributes::only('id,user_id,name,description,is_public,tags')
        );

        (new EnsureAuthorizedUserOwnsResource)($folder);

        $newAttributes = $this->buildFolder($request, $folder);

        $this->ensureCanAddTagsToFolder($folder, $newAttributes->tags);

        $this->updateFolderRepository->update($folder->folderID, $newAttributes);

        event(new FolderModifiedEvent($folder->folderID));
    }

    private function buildFolder(CreateFolderRequest $request, Folder $folder): Folder
    {
        return (new FolderBuilder())
            ->setName($request->validated('name', $folder->name->value))
            ->setDescription($request->validated('description', $folder->description->value))
            ->setIsPublic($request->boolean('is_public', $folder->isPublic))
            ->setTags(TagsCollection::make($request->validated('tags', [])))
            ->build();
    }

    private function ensureCanAddTagsToFolder(Folder $folder, TagsCollection $tags): void
    {
        $canAddMoreTagsToFolder = $folder->tags->count() + $tags->count() <= setting('MAX_FOLDER_TAGS');

        if (!$canAddMoreTagsToFolder) {
            throw new HttpException(['message' => 'Cannot add more tags to bookmark'], Response::HTTP_BAD_REQUEST);
        }

        if ($folder->tags->contains($tags)) {
            throw HttpException::conflict(['message' => 'Duplicate tags']);
        }
    }
}
