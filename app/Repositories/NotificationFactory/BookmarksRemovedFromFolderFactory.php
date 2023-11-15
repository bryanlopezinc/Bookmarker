<?php

declare(strict_types=1);

namespace App\Repositories\NotificationFactory;

use App\DataTransferObjects\Notifications\BookmarksRemovedFromFolder;
use App\Repositories\FetchNotificationResourcesRepository;
use Illuminate\Notifications\DatabaseNotification;

final class BookmarksRemovedFromFolderFactory implements Factory
{
    public function create(FetchNotificationResourcesRepository $repository, DatabaseNotification $notification): object
    {
        return new BookmarksRemovedFromFolder(
            $repository->findFolderByID($notification->data['removed_from_folder']),
            $repository->findUserByID($notification->data['removed_by']),
            $repository->findBookmarksByIDs($notification->data['bookmarks_removed']),
            $notification->id,
            $notification->created_at->toDateTimeString() //@phpstan-ignore-line
        );
    }
}
