<?php

declare(strict_types=1);

namespace App\Http\Handlers\AddBookmarksToFolder;

use App\Models\Folder;
use App\Models\Bookmark;
use App\Enums\ActivityType;
use App\Models\FolderActivity;
use App\DataTransferObjects\AddBookmarksToFolderRequestData as Data;
use Illuminate\Support\Collection;
use App\DataTransferObjects\Activities\NewFolderBookmarksActivityLogData as ActivityLogData;

final class LogActivity
{
    /**
     * @param Collection<Bookmark> $bookmarks
     */
    public function __construct(private readonly Data $data, private readonly Collection $bookmarks)
    {
    }

    public function __invoke(Folder $folder): void
    {
        $activityData = new ActivityLogData($this->bookmarks, $this->data->authUser);

        $attributes = [
            'folder_id' => $folder->id,
            'type'      => ActivityType::NEW_BOOKMARKS,
            'data'      => $activityData->toArray(),
        ];

        dispatch(static function () use ($attributes) {
            FolderActivity::query()->create($attributes);
        })->afterResponse();
    }
}
