<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Collections\TagsCollection;
use App\DataTransferObjects\Builders\FolderBuilder;
use App\DataTransferObjects\Builders\FolderSettingsBuilder as Builder;
use App\DataTransferObjects\FolderSettings;
use App\Http\Requests\CreateFolderRequest;
use App\Repositories\Folder\CreateFolderRepository;
use App\ValueObjects\UserID;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class CreateFolderController
{
    public function __invoke(CreateFolderRequest $request, CreateFolderRepository $repository): JsonResponse
    {
        $folder = (new FolderBuilder())
            ->setCreatedAt(now())
            ->setDescription($request->validated('description'))
            ->setName($request->validated('name'))
            ->setOwnerID(UserID::fromAuthUser())
            ->setIsPublic($request->boolean('is_public'))
            ->setTags(TagsCollection::make($request->validated('tags', [])))
            ->setSettings($this->buildSettings($request))
            ->build();

        $repository->create($folder);

        return response()->json(status: Response::HTTP_CREATED);
    }

    private function buildSettings(CreateFolderRequest $request): FolderSettings
    {
        $default = FolderSettings::default();

        if ($request->validated('settings') === null) {
            return $default;
        }

        return (new Builder())
            ->enableNotifications($request->validated('settings.N-enable', $default->notificationsAreEnabled()))
            ->enableNewCollaboratorNotification($request->validated('settings.N-newCollaborator', $default->newCollaboratorNotificationIsEnabled()))
            ->enableFolderUpdatedNotification($request->validated('settings.N-updated', $default->folderUpdatedNotificationIsEnabled()))
            ->enableNewBookmarksNotification($request->validated('settings.N-newBookmarks', $default->newBookmarksNotificationIsEnabled()))
            ->enableBookmarksRemovedNotification($request->validated('settings.N-bookmarkDelete', $default->bookmarksRemovedNotificationIsEnabled()))
            ->enableCollaboratorExitNotification($request->validated('settings.N-collaboratorExit', $default->collaboratorExitNotificationIsEnabled()))
            ->enableOnlyCollaboratorsInvitedByMeNotification(
                $request->validated(
                    'settings.N-onlyNewCollaboratorsByMe',
                    $default->onlyCollaboratorsInvitedByMeNotificationIsEnabled()
                )
            )
            ->enableOnlyCollaboratorWithWritePermissionNotification(
                $request->validated(
                    'settings.N-collaboratorExitOnlyHasWritePermission',
                    $default->onlyCollaboratorWithWritePermissionNotificationIsEnabled()
                )
            )
            ->build();
    }
}
