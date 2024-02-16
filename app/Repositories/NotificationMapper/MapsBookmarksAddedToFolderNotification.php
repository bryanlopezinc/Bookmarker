<?php

declare(strict_types=1);

namespace App\Repositories\NotificationMapper;

use App\DataTransferObjects\Notifications\NewFolderBookmarksNotificationData;
use App\Repositories\FetchNotificationResourcesRepository;
use App\ValueObjects\FolderName;
use App\ValueObjects\FullName;
use Illuminate\Notifications\DatabaseNotification;

final class MapsBookmarksAddedToFolderNotification implements NotificationMapper
{
    public function map(DatabaseNotification $notification, FetchNotificationResourcesRepository $repository): object
    {
        $data = $notification->data;

        return new NewFolderBookmarksNotificationData(...[
            'folder'               => $repository->findFolderByID($data['folder_id']),
            'collaborator'         => $repository->findUserByID($data['collaborator_id']),
            'collaboratorFullName' => new FullName($data['full_name']),
            'collaboratorId'       => $data['collaborator_id'],
            'folderId'             => $data['folder_id'],
            'folderName'           => new FolderName($data['folder_name']),
            'bookmarks'            => $repository->findBookmarksByIDs($data['bookmark_ids']),
            'notificationId'        => $notification->id,
            'notifiedOn'            => $notification->created_at->toDateTimeString() //@phpstan-ignore-line
        ]);
    }
}
