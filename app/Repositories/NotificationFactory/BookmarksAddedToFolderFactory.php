<?php

declare(strict_types=1);

namespace App\Repositories\NotificationFactory;

use App\DataTransferObjects\Notifications\BookmarksAddedToFolder;
use App\Repositories\FetchNotificationResourcesRepository;
use Illuminate\Notifications\DatabaseNotification;

final class BookmarksAddedToFolderFactory implements Factory
{
    public function create(FetchNotificationResourcesRepository $repository, DatabaseNotification $notification): Object
    {
        return new BookmarksAddedToFolder(
            $repository->findFolderByID($notification->data['added_to_folder']),
            $repository->findUserByID($notification->data['added_by']),
            $repository->findBookmarksByIDs($notification->data['bookmarks_added_to_folder']),
            $notification->id,
            $notification->created_at->toDateTimeString() //@phpstan-ignore-line
        );
    }
}
