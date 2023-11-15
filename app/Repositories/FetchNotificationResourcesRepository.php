<?php

declare(strict_types=1);

namespace App\Repositories;

use Illuminate\Support\Collection;
use App\Models\{Folder, Bookmark, User};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;

/**
 * Retrieve all the resource ids stored in the notification from the database
 * to reduce the amount of database queries.
 */
class FetchNotificationResourcesRepository
{
    /**
     * The key names in the notification data array used to store user ids.
     *
     * @var array<string>
     */
    private const USER_ID_KEYS = [
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
    private const FOLDER_ID_KEYS = [
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
    private const BOOKMARK_ID_KEYS = [
        'bookmarks_added_to_folder',
        'bookmarks_removed',
    ];

    /**
     * The notification resources retrieved from database.
     *
     * @var array<string,Model[]>
     */
    private array $notificationResources = [
        'bookmarks' => [],
        'users'     => [],
        'folders'   => []
    ];

    /**
     * The notifications collection
     */
    private Collection $notifications;

    /**
     * @param Collection<DatabaseNotification> $notifications
     */
    public function __construct(Collection $notifications)
    {
        $this->notifications = $notifications;

        if ($notifications->isEmpty()) {
            return;
        }

        $this->setNotificationResources();
    }

    private function setNotificationResources(): void
    {
        $query = DB::query();

        $query->columns = [];

        if (!empty($bookmarkIds = $this->extractIds('bookmarks'))) {
            $query->addSelect([
                'bookmarks' => Bookmark::query()
                    ->select(DB::raw("JSON_ARRAYAGG(JSON_OBJECT('title', title, 'id', id))"))
                    ->whereIntegerInRaw('id', $bookmarkIds)
            ]);
        }

        if (!empty($userIds = $this->extractIds('users'))) {
            $query->addSelect([
                'users' => User::query()
                    ->select(DB::raw("JSON_ARRAYAGG(JSON_OBJECT('id', id, 'first_name', first_name, 'last_name', last_name))")) //phpcs:ignore
                    ->whereIntegerInRaw('id', $userIds)
            ]);
        }

        if (!empty($folderIds = $this->extractIds('folders'))) {
            $query->addSelect([
                'folders' => Folder::query()
                    ->select(DB::raw("JSON_ARRAYAGG(JSON_OBJECT('name', name, 'id', id))"))
                    ->whereIntegerInRaw('id', $folderIds)
            ]);
        }

        collect($query->get()->first())
            ->mapWithKeys(fn (?string $json, string $key) => [$key => json_decode($json ?? '{}', true)])
            ->mapWithKeys(function (array $data, string $key) {
                $model = match ($key) {
                    'bookmarks' => Bookmark::class,
                    'users'     => User::class,
                    'folders'   => Folder::class
                };

                return [$key => collect($data)->mapInto($model)->all()];
            })
            ->each(fn (array $data, string $key) => $this->notificationResources[$key] = $data);
    }

    private function extractIds(string $type): array
    {
        return $this->notifications
            ->pluck('data')
            ->map(function (array $notificationData) use ($type) {
                $keys = match ($type) {
                    'users'     => self::USER_ID_KEYS,
                    'folders'   => self::FOLDER_ID_KEYS,
                    'bookmarks' => self::BOOKMARK_ID_KEYS,
                };

                return collect($notificationData)->only($keys)->flatten()->unique()->all();
            })
            ->flatten()
            ->all();
    }

    public function findFolderByID(int $id): ?Folder
    {
        return collect($this->notificationResources['folders'])
            ->filter(fn (Folder $folder) => $folder->id === $id)
            ->first();
    }

    public function findUserByID(int $id): ?User
    {
        return collect($this->notificationResources['users'])
            ->filter(fn (User $user) => $user->id === $id)
            ->first();
    }

    /**
     * @param array<int> $ids
     *
     * @return array<Bookmark>
     */
    public function findBookmarksByIDs(array $ids): array
    {
        return collect($this->notificationResources['bookmarks'])
            ->filter(fn (Bookmark $bookmark) => in_array($bookmark->id, $ids, true))
            ->all();
    }
}
