<?php

declare(strict_types=1);

namespace App\Repositories\NotificationFactory;

use App\DataTransferObjects\Notifications\NewCollaborator;
use App\Repositories\FetchNotificationResourcesRepository;
use Illuminate\Notifications\DatabaseNotification;

final class NewCollaboratorFactory implements Factory
{
    public function create(FetchNotificationResourcesRepository $repository, DatabaseNotification $notification): object
    {
        return new NewCollaborator(
            $repository->findUserByID($notification->data['added_by_collaborator']),
            $repository->findFolderByID($notification->data['added_to_folder']),
            $repository->findUserByID($notification->data['new_collaborator_id']),
            $notification->id,
            $notification->created_at->toDateTimeString() //@phpstan-ignore-line
        );
    }
}
