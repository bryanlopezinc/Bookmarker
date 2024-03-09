<?php

declare(strict_types=1);

namespace App\Repositories\NotificationMapper;

use App\DataTransferObjects\Notifications\NewCollaboratorNotificationData;
use App\Repositories\FetchNotificationResourcesRepository;
use App\ValueObjects\FolderName;
use App\ValueObjects\FullName;
use Illuminate\Notifications\DatabaseNotification;

final class MapsNewCollaboratorNotification implements NotificationMapper
{
    public function map(DatabaseNotification $notification, FetchNotificationResourcesRepository $repository): object
    {
        $data = $notification->data;

        /** @var \Carbon\Carbon */
        $notifiedOn = $notification->created_at;

        return new NewCollaboratorNotificationData(...[
            'collaborator'            => $repository->findUserByID($notification->data['collaborator_id']),
            'folder'                  => $repository->findFolderByID($notification->data['folder_id']),
            'newCollaborator'         => $repository->findUserByID($notification->data['new_collaborator_id']),
            'collaboratorId'          => $data['collaborator_id'],
            'newCollaboratorId'       => $data['new_collaborator_id'],
            'collaboratorFullName'    => new FullName($data['collaborator_full_name']),
            'newCollaboratorFullName' => new FullName($data['new_collaborator_full_name']),
            'folderId'                => $data['folder_id'],
            'folderName'              => new FolderName($data['folder_name']),
            'uuid'                    => $notification->id,
            'notifiedOn'               => $notifiedOn->toDateTimeString()
        ]);
    }
}
