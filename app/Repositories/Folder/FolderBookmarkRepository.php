<?php

declare(strict_types=1);

namespace App\Repositories\Folder;

use App\Collections\ResourceIDsCollection as IDs;
use App\Models\Folder as Model;
use App\Models\FolderBookmark as FolderBookmarkModel;
use App\Models\FolderBookmarksCount;
use App\ValueObjects\ResourceID;
use Illuminate\Support\Collection;

final class FolderBookmarkRepository
{
    public function add(ResourceID $folderID, IDs $bookmarkIDs, IDs $makeHidden): void
    {
        $makeHidden = $makeHidden->asIntegers();

        $bookmarkIDs
            ->asIntegers()
            ->map(fn (int $bookmarkID) => [
                'bookmark_id' => $bookmarkID,
                'folder_id' => $folderID->toInt(),
                'is_public' => $makeHidden->containsStrict($bookmarkID) ? false : true
            ])
            ->tap(fn (Collection $data) => FolderBookmarkModel::insert($data->all()));

        $this->incrementFolderBookmarksCount($folderID, $bookmarkIDs->count());

        $this->updateFolderTimeStamp($folderID);
    }

    private function incrementFolderBookmarksCount(ResourceID $folderID, int $amount): void
    {
        $model = FolderBookmarksCount::query()->firstOrCreate(['folder_id' => $folderID->toInt()], ['count' => $amount]);

        if (!$model->wasRecentlyCreated) {
            $model->increment('count', $amount);
        }
    }

    private function updateFolderTimeStamp(ResourceID $folderID): void
    {
        // Folder already exist.
        // @phpstan-ignore-next-line
        Model::query()->whereKey($folderID->toInt())->first()->touch();
    }

    /**
     * @return int number of deleted records.
     */
    public function remove(ResourceID $folderID, IDs $bookmarkIDs): int
    {
        $deleted = FolderBookmarkModel::where('folder_id', $folderID->toInt())->whereIn('bookmark_id', $bookmarkIDs->asIntegers()->all())->delete();

        if ($deleted > 0) {
            $this->updateFolderTimeStamp($folderID);
        }

        return $deleted;
    }

    public function makeHidden(ResourceID $folderID, IDs $bookmarkIDs): void
    {
        FolderBookmarkModel::where('folder_id', $folderID->toInt())
            ->whereIn('bookmark_id', $bookmarkIDs->asIntegers()->all())
            ->update(['is_public' => false]);
    }
}
