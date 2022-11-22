<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Collections\ResourceIDsCollection as IDs;
use Illuminate\Support\Collection;
use App\DataTransferObjects\{Bookmark, DatabaseNotification, User, Folder};
use App\DataTransferObjects\Builders\FolderBuilder;
use App\Models\Folder as FolderModel;
use App\QueryColumns\BookmarkAttributes;
use App\QueryColumns\FolderAttributes;
use App\QueryColumns\UserAttributes;
use Illuminate\Support\Arr;

/**
 * Retrieve all the resource ids stored in the notification from the database
 * to reduce the amount of database queries.
 */
class FetchNotificationResourcesRepository
{
    /**
     * The resource ids from all the notification data.
     *
     * @var array<string,int[]>
     */
    private array $resourceIDs = [
        'folderIDs' => [],
        'bookmarkIDs' => [],
        'userIDs' => []
    ];

    /**
     * The key names in the notification data array used to store user ids.
     *
     * @var array<string>
     */
    private array $userIDKeys = [
        'added_by_collaborator',
        'removed_by',
        'new_collaborator_id',
        'updated_by',
        'exited_by',
        'added_by'
    ];

    /**
     * The key names in the notification data array used to store folder ids.
     *
     * @var array<string>
     */
    private array $folderIDKeys = [
        'added_to_folder',
        'removed_from_folder',
        'exited_from_folder',
        'folder_updated'
    ];

    /**
     * The key names in the notification data array used to store bookmark ids.
     *
     * @var array<string>
     */
    private array $bookmarkIDKeys = [
        'bookmarks_added_to_folder',
        'bookmarks_removed',
    ];

    /**
     * The folders retrieved from database.
     *
     * @var array<int,Folder>
     */
    private array $folders = [];

    /**
     * The bookmarks retrieved from database.
     *
     * @var array<int,Bookmark>
     */
    private array $bookmarks = [];

    /**
     * The users retrieved from database.
     *
     * @var array<int,User>
     */
    private array $users = [];

    /**
     * @param Collection<DatabaseNotification> $notifications
     */
    public function __construct(Collection $notifications)
    {
        if ($notifications->isEmpty()) {
            return;
        }

        $this->extractResourceIDsFromNotifications($notifications);
        $this->fetchResourcesByIDs();
    }

    private function extractResourceIDsFromNotifications(Collection $notifications): void
    {
        $notifications->each(function (DatabaseNotification $notification) {
            $data = $notification->notificationData->data;

            $this->setResourceIDs('userIDs', Arr::only($data, $this->userIDKeys));
            $this->setResourceIDs('folderIDs', Arr::only($data, $this->folderIDKeys));
            $this->setResourceIDs('bookmarkIDs', Arr::only($data, $this->bookmarkIDKeys));
        });
    }

    private function fetchResourcesByIDs(): void
    {
        collect($this->resourceIDs['bookmarkIDs'])
            ->unique()
            ->whenNotEmpty(function (Collection $bookmarkIDs) {
                /** @var BookmarkRepository */
                $repository = app(BookmarkRepository::class);

                $repository->findManyById(IDs::fromNativeTypes($bookmarkIDs), BookmarkAttributes::only('title,id'))
                    ->each(fn (Bookmark $bookmark) => $this->bookmarks[$bookmark->id->value()] = $bookmark);
            });

        collect($this->resourceIDs['userIDs'])
            ->unique()
            ->whenNotEmpty(function (Collection $userIDs) {
                /** @var UserRepository */
                $repository = app(UserRepository::class);

                $repository->findManyByIDs(IDs::fromNativeTypes($userIDs), UserAttributes::only('id,firstname,lastname'))
                    ->each(fn (User $user) => $this->users[$user->id->value()] = $user);
            });

        collect($this->resourceIDs['folderIDs'])
            ->unique()
            ->whenNotEmpty(function (Collection $folderIDs) {
                return FolderModel::onlyAttributes(FolderAttributes::only('id,name'))
                    ->find($folderIDs)
                    ->map(fn (FolderModel $folder) => FolderBuilder::fromModel($folder)->build())
                    ->each(fn (Folder $folder) => $this->folders[$folder->folderID->value()] = $folder);
            });
    }

    private function setResourceIDs(string $type, array $ids): void
    {
        collect($ids)
            ->flatten()
            ->values()
            ->whenNotEmpty(function (Collection $ids) use ($type) {
                $this->resourceIDs[$type] = array_merge($this->resourceIDs[$type], $ids->all());
            });
    }

    public function findFolderByID(int $id): ?Folder
    {
        return $this->folders[$id] ?? null;
    }

    public function findUserByID(int $id): ?User
    {
        return $this->users[$id] ?? null;
    }

    /**
     * @param array<int> $ids
     * @return array<Bookmark>
     */
    public function findBookmarksByIDs(array $ids): array
    {
        return array_intersect_key($this->bookmarks, array_flip($ids));
    }
}
