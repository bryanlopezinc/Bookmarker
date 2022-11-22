<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\TransformsNotificationInterface;
use App\DataTransferObjects\DatabaseNotification;
use App\Enums\NotificationType as Type;
use App\Http\Resources\Notifications\NewCollaboratorNotificationResource;
use App\Http\Resources\Notifications\BookmarksAddedToFolderNotificationResource;
use App\Http\Resources\Notifications\CollaboratorExitNotificationResource;
use App\Http\Resources\Notifications\FolderBookmarksRemovedNotificationResource;
use App\Http\Resources\Notifications\FolderUpdatedNotificationResource;

final class SelectNotificationObject
{
    public function __construct(private FetchNotificationResourcesRepository $repository)
    {
    }

    public function __invoke(DatabaseNotification $notification): TransformsNotificationInterface
    {
        return match ($notification->notificationType) {
            Type::BOOKMARKS_ADDED_TO_FOLDER => new BookmarksAddedToFolderNotificationResource($notification, $this->repository),
            Type::BOOKMARKS_REMOVED_FROM_FOLDER => new FolderBookmarksRemovedNotificationResource($notification, $this->repository),
            Type::NEW_COLLABORATOR => new NewCollaboratorNotificationResource($notification, $this->repository),
            Type::FOLDER_UPDATED => new FolderUpdatedNotificationResource($notification, $this->repository),
            Type::COLLABORATOR_EXIT => new CollaboratorExitNotificationResource($notification, $this->repository)
        };
    }
}
