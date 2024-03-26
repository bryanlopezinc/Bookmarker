<?php

declare(strict_types=1);

namespace App\Repositories\NotificationMapper;

use App\DataTransferObjects\Notifications\CollaboratorRemovedNotificationData;
use App\Repositories\FetchNotificationResourcesRepository;
use App\ValueObjects\FolderName;
use App\ValueObjects\FullName;
use Illuminate\Notifications\DatabaseNotification;

final class CollaboratorRemovedNotificationMapper implements NotificationMapper
{
    public function map(DatabaseNotification $notification, FetchNotificationResourcesRepository $repository): object
    {
        $data = $notification->data;

        /** @var \Carbon\Carbon */
        $notifiedOn = $notification->created_at;

        return new CollaboratorRemovedNotificationData(...[
            'folder'               => $repository->findFolderByID($data['folder']['id']),
            'folderId'             => $data['folder']['id'],
            'folderName'           => new FolderName($data['folder']['name']),
            'collaboratorId'       => $data['collaborator']['id'],
            'collaborator'         => $repository->findUserByID($data['collaborator']['id']),
            'collaboratorFullName' => new FullName($data['collaborator']['name']),
            'removedById'          => $data['removed_by']['id'],
            'removedBy'            => $repository->findUserByID($data['removed_by']['id']),
            'removedByFullName'    => new FullName($data['removed_by']['name']),
            'uuid'                 => $notification->id,
            'notifiedOn'            => $notifiedOn->toDateTimeString(),
            'wasBanned'            => $data['was_banned']
        ]);
    }
}
