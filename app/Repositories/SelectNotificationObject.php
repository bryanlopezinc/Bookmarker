<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DataTransferObjects\Notifications\BookmarksAddedToFolder;
use App\DataTransferObjects\Notifications\BookmarksRemovedFromFolder;
use App\DataTransferObjects\Notifications\CollaboratorExit;
use App\DataTransferObjects\Notifications\FolderUpdated;
use App\DataTransferObjects\Notifications\NewCollaborator;
use App\Enums\NotificationType as Type;
use Illuminate\Notifications\DatabaseNotification;

final class SelectNotificationObject
{
    public function __construct(private FetchNotificationResourcesRepository $repository)
    {
    }

    public function __invoke(DatabaseNotification $notification)
    {
        return match (Type::from($notification->type)) {
            Type::BOOKMARKS_ADDED_TO_FOLDER => new BookmarksAddedToFolder(
                $this->repository->findFolderByID($notification->data['added_to_folder']),
                $this->repository->findUserByID($notification->data['added_by']),
                $this->repository->findBookmarksByIDs($notification->data['bookmarks_added_to_folder']),
                $notification->id
            ),

            Type::BOOKMARKS_REMOVED_FROM_FOLDER => new BookmarksRemovedFromFolder(
                $this->repository->findFolderByID($notification->data['removed_from_folder']),
                $this->repository->findUserByID($notification->data['removed_by']),
                $this->repository->findBookmarksByIDs($notification->data['bookmarks_removed']),
                $notification->id
            ),

            Type::NEW_COLLABORATOR => new NewCollaborator(
                $this->repository->findUserByID($notification->data['added_by_collaborator']),
                $this->repository->findFolderByID($notification->data['added_to_folder']),
                $this->repository->findUserByID($notification->data['new_collaborator_id']),
                $notification->id
            ),

            Type::FOLDER_UPDATED => new FolderUpdated(
                $this->repository->findFolderByID($notification->data['folder_updated']),
                $this->repository->findUserByID($notification->data['updated_by']),
                $notification->data['changes'],
                $notification->id
            ),

            Type::COLLABORATOR_EXIT => new CollaboratorExit(
                $this->repository->findUserByID($notification->data['exited_by']),
                $this->repository->findFolderByID($notification->data['exited_from_folder']),
                $notification->id
            )
        };
    }
}
