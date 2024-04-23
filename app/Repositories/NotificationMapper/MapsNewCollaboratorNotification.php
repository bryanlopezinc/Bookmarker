<?php

declare(strict_types=1);

namespace App\Repositories\NotificationMapper;

use App\DataTransferObjects\Notifications\NewCollaboratorNotificationData;
use App\Repositories\FetchNotificationResourcesRepository;
use App\ValueObjects\FolderName;
use App\ValueObjects\PublicId\FolderPublicId;
use App\ValueObjects\FullName;
use App\ValueObjects\PublicId\UserPublicId;
use Illuminate\Config\Repository;
use Illuminate\Notifications\DatabaseNotification;

final class MapsNewCollaboratorNotification implements NotificationMapper
{
    public function map(DatabaseNotification $notification, FetchNotificationResourcesRepository $repository): object
    {
        $data = new Repository($notification->data);

        /** @var \Carbon\Carbon */
        $notifiedOn = $notification->created_at;

        return new NewCollaboratorNotificationData(...[
            'collaborator'            => $repository->findUserByID($data['collaborator.id']),
            'folder'                  => $repository->findFolderByID($data['folder.id']),
            'newCollaborator'         => $repository->findUserByID($data['new_collaborator.id']),
            'collaboratorId'          => new UserPublicId($data['collaborator.public_id']),
            'newCollaboratorId'       => new UserPublicId($data['new_collaborator.public_id']),
            'collaboratorFullName'    => new FullName($data['collaborator.full_name']),
            'newCollaboratorFullName' => new FullName($data['new_collaborator.full_name']),
            'folderId'                => new FolderPublicId($data['folder.public_id']),
            'folderName'              => new FolderName($data['folder.name']),
            'uuid'                    => $notification->id,
            'notifiedOn'               => $notifiedOn->toDateTimeString()
        ]);
    }
}
