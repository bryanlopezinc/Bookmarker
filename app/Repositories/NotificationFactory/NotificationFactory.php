<?php

declare(strict_types=1);

namespace App\Repositories\NotificationFactory;

use App\Repositories\FetchNotificationResourcesRepository;
use Illuminate\Notifications\DatabaseNotification;

final class NotificationFactory
{
    private const FACTORIES = [
        'FolderUpdated'              => FolderUpdatedFactory::class,
        'bookmarksAddedToFolder'     => BookmarksAddedToFolderFactory::class,
        'bookmarksRemovedFromFolder' => BookmarksRemovedFromFolderFactory::class,
        'collaboratorExitedFolder'   => CollaboratorExitFactory::class,
        'collaboratorAddedToFolder'  => NewCollaboratorFactory::class,
    ];

    public function __construct(private FetchNotificationResourcesRepository $repository)
    {
    }

    public function __invoke(DatabaseNotification $notification)
    {
        /** @var Factory */
        $factory = new (self::FACTORIES[$notification->type]);

        return $factory->create($this->repository, $notification);
    }
}
