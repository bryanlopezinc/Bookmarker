<?php

declare(strict_types=1);

namespace App\Http\Handlers\RemoveFolderBookmarks;

use App\DataTransferObjects\Activities\FolderBookmarksRemovedActivityLogData;
use App\Enums\ActivityType;
use App\Models\Bookmark;
use App\Models\Folder;
use App\Models\FolderActivity;
use App\Models\User;

final class LogActivity
{
    /**
     * @param array<Bookmark> $folderBookmarks
     */
    public function __construct(private readonly array $folderBookmarks, private readonly User $authUser)
    {
    }

    public function __invoke(Folder $folder): void
    {
        $attributes = [
            'folderId'  => $folder->id,
            'bookmarks' => collect($this->folderBookmarks)->map->getAttributes(),
            'authUser'  => $this->authUser->activityLogContextVariables()
        ];

        dispatch(static function () use ($attributes) {
            $logData = (new FolderBookmarksRemovedActivityLogData(
                $attributes['bookmarks']->mapInto(Bookmark::class),
                new User($attributes['authUser'])
            ));

            FolderActivity::query()->create([
                'folder_id' => $attributes['folderId'],
                'type'      => ActivityType::BOOKMARKS_REMOVED,
                'data'      => $logData->toArray(),
            ]);
        })->afterResponse();
    }
}
