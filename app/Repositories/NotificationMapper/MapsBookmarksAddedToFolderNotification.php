<?php

declare(strict_types=1);

namespace App\Repositories\NotificationMapper;

use App\DataTransferObjects\Notifications\NewFolderBookmarksNotificationData;
use App\Repositories\FetchNotificationResourcesRepository;
use App\ValueObjects\FolderName;
use App\ValueObjects\PublicId\FolderPublicId;
use App\ValueObjects\FullName;
use App\ValueObjects\PublicId\UserPublicId;
use Illuminate\Config\Repository;
use Illuminate\Notifications\DatabaseNotification;

final class MapsBookmarksAddedToFolderNotification implements NotificationMapper
{
    public function map(DatabaseNotification $notification, FetchNotificationResourcesRepository $repository): object
    {
        $data = new Repository($notification->data);

        /** @var \Carbon\Carbon */
        $notifiedOn = $notification->created_at;

        return new NewFolderBookmarksNotificationData(...[
            'folder'               => $repository->findFolderByID($data['folder.id']),
            'collaborator'         => $repository->findUserByID($data['collaborator.id']),
            'collaboratorFullName' => new FullName($data['collaborator.full_name']),
            'collaboratorId'       => new UserPublicId($data['collaborator.public_id']),
            'folderId'             => new FolderPublicId($data['folder.public_id']),
            'folderName'           => new FolderName($data['folder.name']),
            'bookmarks'            => $repository->findBookmarksByIDs($data['bookmark_ids']),
            'notificationId'        => $notification->id,
            'notifiedOn'            => $notifiedOn->toDateTimeString()
        ]);
    }
}
