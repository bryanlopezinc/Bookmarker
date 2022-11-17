<?php

declare(strict_types=1);

namespace App\Services\Folder;

use App\Collections\TagsCollection;
use App\Contracts\FolderRepositoryInterface;
use App\DataTransferObjects\Builders\FolderBuilder;
use App\DataTransferObjects\Folder;
use App\Events\FolderModifiedEvent;
use App\Exceptions\HttpException;
use App\Http\Requests\CreateFolderRequest as Request;
use App\Policies\EnsureAuthorizedUserOwnsResource;
use App\ValueObjects\ResourceID;
use App\QueryColumns\FolderAttributes as Attributes;
use App\Repositories\Folder\FolderPermissionsRepository;
use App\Repositories\Folder\FolderRepository;
use App\ValueObjects\UserID;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\HttpException as SymfonyHttpException;
use App\Notifications\FolderUpdatedNotification as Notification;

final class UpdateFolderService
{
    public function __construct(
        private FolderRepositoryInterface $folderRepository,
        private FolderRepository $updateFolderRepository,
        private FolderPermissionsRepository $permissions
    ) {
    }

    public function fromRequest(Request $request): void
    {
        $folder = $this->folderRepository->find(
            ResourceID::fromRequest($request, 'folder'),
            Attributes::only('id,user_id,name,description,is_public,tags,settings')
        );

        $this->ensureUserCanUpdateFolder($folder, $request);

        $newAttributes = $this->buildFolder($request, $folder);

        $this->updateFolderRepository->update($folder->folderID, $newAttributes);

        event(new FolderModifiedEvent($folder->folderID));

        $this->notifyFolderOwner($folder, $newAttributes);
    }

    private function ensureUserCanUpdateFolder(Folder $folder, Request $request): void
    {
        try {
            (new EnsureAuthorizedUserOwnsResource)($folder);
        } catch (SymfonyHttpException $e) {
            $collaboratorHasUpdateFolderPermission = $this->permissions
                ->getUserAccessControls(UserID::fromAuthUser(), $folder->folderID)
                ->canUpdateFolder();

            if (!$collaboratorHasUpdateFolderPermission || $request->has('is_public')) {
                throw $e;
            }
        }
    }

    private function buildFolder(Request $request, Folder $folder): Folder
    {
        $this->confirmPasswordBeforeUpdatingPrivacy($request);

        $this->ensureCanAddTagsToFolder($folder, $request);

        return (new FolderBuilder())
            ->setName($request->validated('name', $folder->name->value))
            ->setDescription($request->validated('description', $folder->description->value))
            ->setIsPublic($request->boolean('is_public', $folder->isPublic))
            ->setTags(TagsCollection::make($request->validated('tags', [])))
            ->build();
    }

    private function ensureCanAddTagsToFolder(Folder $folder, Request $request): void
    {
        $newTags = TagsCollection::make($request->validated('tags', []));
        $canAddMoreTagsToFolder = $folder->tags->count() + $newTags->count() <= setting('MAX_FOLDER_TAGS');

        if (!$canAddMoreTagsToFolder) {
            throw new HttpException([
                'message' => 'Cannot add more tags to bookmark'
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($folder->tags->contains($newTags)) {
            throw HttpException::conflict(['message' => 'Duplicate tags']);
        }
    }

    private function confirmPasswordBeforeUpdatingPrivacy(Request $request): void
    {
        $key = 'f:update:cP' . auth('api')->id();

        if (!$request->has('is_public') || Cache::has($key)) {
            return;
        }

        if (!$request->has('password')) {
            throw new HttpException([
                'message' => 'Password confirmation required.'
            ], Response::HTTP_LOCKED);
        }

        if (!Hash::check($request->input('password'), auth('api')->user()->getAuthPassword())) {  // @phpstan-ignore-line
            throw HttpException::unAuthorized(['message' => 'Invalid password']);
        }

        Cache::put($key, true, now()->addHour());
    }

    private function notifyFolderOwner(Folder $original, Folder $updated): void
    {
        $folderWasUpdatedByOwner = $original->ownerID->equals($collaboratorID = UserID::fromAuthUser());
        $notification = new Notification($original, $updated, $collaboratorID);

        if (
            $folderWasUpdatedByOwner ||
            !$original->settings->receiveNotifications() ||
            !$original->settings->receiveNewUpdateNotifications()
        ) {
            return;
        }

        (new \App\Models\User(['id' => $original->ownerID->value()]))->notify($notification);
    }
}
