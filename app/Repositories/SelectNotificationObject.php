<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\TransformsNotificationInterface;
use App\Http\Resources\Notifications\NewCollaboratorNotificationResource;
use App\Http\Resources\Notifications\BookmarksAddedToFolderNotificationResource;
use App\Http\Resources\Notifications\FolderBookmarksRemovedNotificationResource;
use App\Http\Resources\Notifications\FolderUpdatedNotificationResource;
use Illuminate\Notifications\DatabaseNotification;
use App\Notifications\BookmarksAddedToFolderNotification as BTF;
use App\Notifications\BookmarksRemovedFromFolderNotification as BRF;
use App\Notifications\NewCollaboratorNotification as CF;
use App\Notifications\FolderUpdatedNotification as FUN;

final class SelectNotificationObject
{
    public function __construct(private FetchNotificationResourcesRepository $repository)
    {
    }

    public function __invoke(DatabaseNotification $notification): TransformsNotificationInterface
    {
        return match ($notification->type) {
            BTF::TYPE => new BookmarksAddedToFolderNotificationResource($notification, $this->repository),
            BRF::TYPE => new FolderBookmarksRemovedNotificationResource($notification, $this->repository),
            CF::TYPE => new NewCollaboratorNotificationResource($notification, $this->repository),
            FUN::TYPE => new FolderUpdatedNotificationResource($notification, $this->repository)
        };
    }
}
