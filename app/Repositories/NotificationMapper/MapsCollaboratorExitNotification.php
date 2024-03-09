<?php

declare(strict_types=1);

namespace App\Repositories\NotificationMapper;

use App\DataTransferObjects\Notifications\CollaboratorExitNotificationData;
use App\Repositories\FetchNotificationResourcesRepository;
use App\ValueObjects\FolderName;
use App\ValueObjects\FullName;
use Illuminate\Notifications\DatabaseNotification;

final class MapsCollaboratorExitNotification implements NotificationMapper
{
    public function map(DatabaseNotification $notification, FetchNotificationResourcesRepository $repository): object
    {
        $data = $notification->data;

        /** @var \Carbon\Carbon */
        $notifiedOn = $notification->created_at;

        return new CollaboratorExitNotificationData(...[
            'collaborator'         => $repository->findUserByID($data['collaborator_id']),
            'folder'               => $repository->findFolderByID($data['folder_id']),
            'folderId'             => $data['folder_id'],
            'collaboratorId'       => $data['collaborator_id'],
            'folderName'           => new FolderName($data['folder_name']),
            'collaboratorFullName' => new FullName($data['collaborator_full_name']),
            'uuid'                 => $notification->id,
            'notifiedOn'            => $notifiedOn->toDateTimeString()
        ]);
    }
}
