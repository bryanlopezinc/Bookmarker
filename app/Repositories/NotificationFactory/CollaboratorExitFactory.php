<?php

declare(strict_types=1);

namespace App\Repositories\NotificationFactory;

use App\DataTransferObjects\Notifications\CollaboratorExit;
use App\Repositories\FetchNotificationResourcesRepository;
use Illuminate\Notifications\DatabaseNotification;

final class CollaboratorExitFactory implements Factory
{
    public function create(FetchNotificationResourcesRepository $repository, DatabaseNotification $notification): Object
    {
        return new CollaboratorExit(
            $repository->findUserByID($notification->data['exited_by']),
            $repository->findFolderByID($notification->data['exited_from_folder']),
            $notification->id,
            $notification->created_at->toDateTimeString() //@phpstan-ignore-line
        );
    }
}
