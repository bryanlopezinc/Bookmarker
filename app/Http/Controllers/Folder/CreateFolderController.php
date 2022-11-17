<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Collections\TagsCollection;
use App\DataTransferObjects\Builders\FolderBuilder;
use App\DataTransferObjects\Builders\FolderSettingsBuilder;
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

        return (new FolderSettingsBuilder())
            ->enableNotifications($request->validated('settings.N-enable', $default->receiveNotifications()))
            ->notifyOnNewCollaborator($request->validated('settings.N-newCollaborator', $default->receiveNewCollaboratorNotifications()))
            ->notifyOnFolderUpdate($request->validated('settings.N-updated', $default->receiveNewUpdateNotifications()))
            ->notifyOnNewBookmarks($request->validated('settings.N-newBookmarks', $default->receiveNewBookmarksNotifications()))
            ->notifyOnBookmarksRemoved($request->validated('settings.N-bookmarkDelete', $default->receiveBookmarksRemovedNotifications()))
            ->notifyOnCollaboratorExit($request->validated('settings.N-collaboratorExit', $default->receiveCollaboratorExitNotifications()))
            ->notifyOnNewCollaboratorOnlyInvitedByMe(
                $request->validated(
                    'settings.N-onlyNewCollaboratorsByMe',
                    $default->receiveOnlyNewCollaboratorInvitedByMeNotifications()
                )
            )
            ->notifyOnCollaboratorExitOnlyWhenHasWritePermission(
                $request->validated(
                    'settings.N-collaboratorExitOnlyHasWritePermission',
                    $default->receiveCollaboratorExitNotificationsWhenHasWritePermission()
                )
            )
            ->build();
    }
}
