<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Collections\TagsCollection;
use App\DataTransferObjects\Builders\FolderBuilder;
use App\DataTransferObjects\Folder;
use App\Exceptions\HttpException;
use App\Http\Requests\CreateFolderRequest;
use App\Policies\EnsureAuthorizedUserOwnsResource;
use App\Repositories\Folder\FolderRepository;
use App\ValueObjects\ResourceID;
use App\QueryColumns\FolderAttributes as Attributes;
use App\Repositories\Folder\UpdateFolderRepository;
use Illuminate\Http\Response;

final class UpdateFolderService
{
    public function __construct(
        private FolderRepository $folderRepository,
        private UpdateFolderRepository $updateFolderRepository
    ) {
    }

    public function fromRequest(CreateFolderRequest $request): void
    {
        $folder = $this->folderRepository->find(
            ResourceID::fromRequest($request, 'folder'),
            Attributes::only('id,userId,name,description,privacy,tags')
        );

        (new EnsureAuthorizedUserOwnsResource)($folder);

        $newAttributes = $this->buildFolder($request, $folder);

        $this->ensureCanAddTagsToFolder($folder, $newAttributes->tags);

        $this->updateFolderRepository->update($folder->folderID, $newAttributes);
    }

    private function buildFolder(CreateFolderRequest $request, Folder $folder): Folder
    {
        return (new FolderBuilder())
            ->setName($request->validated('name', $folder->name->value))
            ->setDescription($request->validated('description', $folder->description->value))
            ->setisPublic($request->boolean('is_public', $folder->isPublic))
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
