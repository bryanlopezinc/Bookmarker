<?php

declare(strict_types=1);

namespace App\Repositories\NotificationMapper;

use App\DataTransferObjects\Notifications\YouHaveBeenKickedOutNotificationData;
use App\Repositories\FetchNotificationResourcesRepository;
use App\ValueObjects\FolderName;
use App\ValueObjects\PublicId\FolderPublicId;
use Illuminate\Config\Repository;
use Illuminate\Notifications\DatabaseNotification;

final class MapsYouHaveBeenKickedOutNotification implements NotificationMapper
{
    public function map(DatabaseNotification $notification, FetchNotificationResourcesRepository $repository): object
    {
        $data = new Repository($notification->data);

        /** @var \Carbon\Carbon */
        $notifiedOn = $notification->created_at;

        return new YouHaveBeenKickedOutNotificationData(...[
            'folder'     => $repository->findFolderByID($data['folder.id']),
            'folderId'   => new FolderPublicId($data['folder.public_id']),
            'folderName' => new FolderName($data['folder.name']),
            'uuid'       => $notification->id,
            'notifiedOn'  => $notifiedOn->toDateTimeString()
        ]);
    }
}
