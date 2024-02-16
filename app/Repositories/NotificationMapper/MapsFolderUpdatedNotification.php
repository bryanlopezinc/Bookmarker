<?php

declare(strict_types=1);

namespace App\Repositories\NotificationMapper;

use App\DataTransferObjects\Notifications\FolderUpdatedNotificationData;
use App\Repositories\FetchNotificationResourcesRepository;
use App\ValueObjects\FolderName;
use App\ValueObjects\FullName;
use Illuminate\Notifications\DatabaseNotification;

final class MapsFolderUpdatedNotification implements NotificationMapper
{
    public function map(DatabaseNotification $notification, FetchNotificationResourcesRepository $repository): object
    {
        $data = $notification->data;

        return new FolderUpdatedNotificationData(...[
            'folder'               => $repository->findFolderByID($data['folder_id']),
            'collaborator'         => $repository->findUserByID($data['collaborator_id']),
            'collaboratorFullName' => new FullName($data['collaborator_full_name']),
            'folderName'           => new FolderName($data['folder_name']),
            'folderId'             => $data['folder_id'],
            'collaboratorId'       => $data['collaborator_id'],
            'changes'              => $notification->data['changes'],
            'uuid'                 => $notification->id,
            'notifiedOn'            => $notification->created_at->toDateTimeString(), //@phpstan-ignore-line
            'modifiedAttribute'     => $data['modified']
        ]);
    }
}
