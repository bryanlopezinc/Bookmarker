<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\NotificationType;
use App\Repositories\FetchNotificationResourcesRepository as Repository;
use Illuminate\Notifications\DatabaseNotification;
use App\Repositories\NotificationMapper\NotificationMapper as NotificationMapperInterface;
use App\Repositories\NotificationMapper as Mappers;
use Illuminate\Support\Collection;

final class NotificationMapper
{
    /**
     * @var array<string,class-string<NotificationMapperInterface>>
     */
    private const NOTIFICATION_MAPPERS = [
        'BookmarksAddedToFolder'     => Mappers\MapsBookmarksAddedToFolderNotification::class,
        'BookmarksRemovedFromFolder' => Mappers\MapsBookmarksRemovedFromFolderNotification::class,
        'CollaboratorAddedToFolder'  => Mappers\MapsNewCollaboratorNotification::class,
        'CollaboratorExitedFolder'   => Mappers\MapsCollaboratorExitNotification::class,
        'FolderUpdated'              => Mappers\MapsFolderUpdatedNotification::class,
        'ImportFailed'               => Mappers\SimpleNotificationMapper::class,
        'YouHaveBeenKickedOut'       => Mappers\MapsYouHaveBeenKickedOutNotification::class,
        'CollaboratorRemoved'        => Mappers\CollaboratorRemovedNotificationMapper::class
    ];

    /**
     * @var array<string,NotificationMapperInterface>
     */
    private array $notificationMapperInstances = [];

    /**
     * @param Collection<DatabaseNotification> $notifications
     */
    public function map(Collection $notifications): Collection
    {
        $repository = new Repository($notifications);

        return $notifications->map(function (DatabaseNotification $notification) use ($repository) {
            $type = NotificationType::from($notification->type);

            return $this->getMapperForType($type)->map($notification, $repository);
        });
    }

    private function getMapperForType(NotificationType $notificationType): NotificationMapperInterface
    {
        $type = $notificationType->value;

        $mapper = $this->notificationMapperInstances[$type] ?? self::NOTIFICATION_MAPPERS[$type];

        if (is_object($mapper)) {
            return $mapper;
        }

        return $this->notificationMapperInstances[$type] = new $mapper();
    }
}
