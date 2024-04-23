<?php

declare(strict_types=1);

namespace App\Repositories;

use Illuminate\Support\Collection;
use App\Models\{Folder, Bookmark, User};
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Retrieve all the resource ids stored in the notification from the database
 * to reduce the amount of database queries.
 */
final class FetchNotificationResourcesRepository
{
    /**
     * The key names in the notification data array used to store user ids.
     *
     * @var array<string>
     */
    private const USER_ID_KEYS = [
        'collaborator_id',
        'new_collaborator.id',
        'collaborator.id',
        'removed_by.id'
    ];

    /**
     * The key names in the notification data array used to store folder ids.
     *
     * @var array<string>
     */
    private const FOLDER_ID_KEYS = ['folder_id', 'folder.id'];

    /**
     * The key names in the notification data array used to store bookmark ids.
     *
     * @var array<string>
     */
    private const BOOKMARK_ID_KEYS = ['bookmark_ids'];

    private array $notificationResources = [
        'bookmarks' => [],
        'users'     => [],
        'folders'   => []
    ];

    /**
     * The notifications collection
     *
     * @var Collection<DatabaseNotification>
     */
    private Collection $notifications;

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

        if ( ! empty($bookmarkIds = $this->extractIds('bookmarks'))) {
            $query->addSelect([
                'bookmarks' => Bookmark::query()
                    ->select(DB::raw("JSON_ARRAYAGG(JSON_OBJECT('title', title, 'id', id))"))
                    ->whereIntegerInRaw('id', $bookmarkIds)
            ]);
        }

        if ( ! empty($userIds = $this->extractIds('users'))) {
            $query->addSelect([
                'users' => User::query()
                    ->select(DB::raw("JSON_ARRAYAGG(JSON_OBJECT('id', id, 'full_name', full_name, 'profile_image_path', profile_image_path))"))
                    ->whereIntegerInRaw('id', $userIds)
            ]);
        }

        if ( ! empty($folderIds = $this->extractIds('folders'))) {
            $query->addSelect([
                'folders' => Folder::query()
                    ->select(DB::raw("JSON_ARRAYAGG(JSON_OBJECT('name', name, 'id', id, 'public_id', public_id))"))
                    ->whereIntegerInRaw('id', $folderIds)
            ]);
        }

        if (empty($query->columns)) {
            return;
        }

        collect((array) $query->first())
            ->mapWithKeys(fn (?string $json, string $key) => [$key => json_decode($json ?? '{}', true)])
            ->mapWithKeys(function (array $data, string $key) {
                $model = match ($key) {
                    'bookmarks' => Bookmark::class,
                    'users'     => User::class,
                    default     => Folder::class
                };

                return [$key => collect($data)->mapInto($model)->all()];
            })
            ->each(function ($data, string $key) {
                $this->notificationResources[$key] = $data;
            });
    }

    private function extractIds(string $type): array
    {
        $keys = match ($type) {
            'users'   => self::USER_ID_KEYS,
            'folders' => self::FOLDER_ID_KEYS,
            default   => self::BOOKMARK_ID_KEYS,
        };

        return $this->notifications
            ->pluck('data')
            ->map(function (array $notificationData) use ($keys) {
                $ids = [];

                foreach ($keys as $key) {
                    if ( ! Arr::has($notificationData, $key)) {
                        continue;
                    }

                    $ids[] = Arr::get($notificationData, $key);
                }

                return $ids;
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
