<?php

declare(strict_types=1);

namespace App\Repositories\NotificationMapper;

use App\DataTransferObjects\Notifications\YouHaveBeenKickedOutNotificationData;
use App\Repositories\FetchNotificationResourcesRepository;
use App\ValueObjects\FolderName;
use Illuminate\Notifications\DatabaseNotification;

final class MapsYouHaveBeenKickedOutNotification implements NotificationMapper
{
    public function map(DatabaseNotification $notification, FetchNotificationResourcesRepository $repository): object
    {
        $data = $notification->data;

        /** @var \Carbon\Carbon */
        $notifiedOn = $notification->created_at;

        return new YouHaveBeenKickedOutNotificationData(...[
            'folder'     => $repository->findFolderByID($data['folder_id']),
            'folderId'   => $data['folder_id'],
            'folderName' => new FolderName($data['folder_name']),
            'uuid'       => $notification->id,
            'notifiedOn'  => $notifiedOn->toDateTimeString()
        ]);
    }
}
