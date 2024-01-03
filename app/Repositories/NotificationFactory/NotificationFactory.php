<?php

declare(strict_types=1);

namespace App\Repositories\NotificationFactory;

use App\Enums\NotificationType;
use App\Repositories\FetchNotificationResourcesRepository;
use Illuminate\Notifications\DatabaseNotification;

final class NotificationFactory
{
    public function __construct(private FetchNotificationResourcesRepository $repository)
    {
    }

    public function __invoke(DatabaseNotification $notification): object
    {
        /** @var Factory */
        $factory = match (NotificationType::from($notification->type)) {
            NotificationType::FOLDER_UPDATED                => new FolderUpdatedFactory(),
            NotificationType::BOOKMARKS_ADDED_TO_FOLDER     => new BookmarksAddedToFolderFactory(),
            NotificationType::BOOKMARKS_REMOVED_FROM_FOLDER => new BookmarksRemovedFromFolderFactory(),
            NotificationType::COLLABORATOR_EXIT             => new CollaboratorExitFactory(),
            NotificationType::NEW_COLLABORATOR              => new NewCollaboratorFactory(),
            default                                         => $notification
        };

        if ($factory instanceof DatabaseNotification) {
            return $notification;
        }

        return $factory->create($this->repository, $notification);
    }
}
