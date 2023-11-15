<?php

declare(strict_types=1);

namespace App\Repositories\NotificationFactory;

use App\DataTransferObjects\Notifications\FolderUpdated;
use App\Repositories\FetchNotificationResourcesRepository;
use Illuminate\Notifications\DatabaseNotification;

final class FolderUpdatedFactory implements Factory
{
    public function create(FetchNotificationResourcesRepository $repository, DatabaseNotification $notification): object
    {
        return new FolderUpdated(
            $repository->findFolderByID($notification->data['folder_updated']),
            $repository->findUserByID($notification->data['updated_by']),
            $notification->data['changes'],
            $notification->id,
            $notification->created_at->toDateTimeString() //@phpstan-ignore-line
        );
    }
}
