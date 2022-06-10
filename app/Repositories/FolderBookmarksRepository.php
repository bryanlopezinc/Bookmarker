<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Collections\ResourceIDsCollection;
use App\Models\Folder as Model;
use App\Models\FolderBookmark;
use App\Models\FolderBookmarksCount;
use App\ValueObjects\ResourceID;
use Illuminate\Support\Collection;

final class FolderBookmarksRepository
{
    /**
     * Get all the bookmarkIDs that already exists in  given folder from the given bookmark ids.
     */
    public function getFolderBookmarksFrom(ResourceID $folderID, ResourceIDsCollection $bookmarkIDs): ResourceIDsCollection
    {
        return FolderBookmark::where('folder_id', $folderID->toInt())
            ->whereIn('bookmark_id', $bookmarkIDs->asIntegers()->unique()->all())
            ->get('bookmark_id')
            ->pipe(fn (Collection $bookmarkIDs) => ResourceIDsCollection::fromNativeTypes($bookmarkIDs->pluck('bookmark_id')->all()));
    }

    public function addBookmarksToFolder(ResourceID $folderID, ResourceIDsCollection $bookmarkIDs): void
    {
        $bookmarkIDs
            ->asIntegers()
            ->map(fn (int $bookmarkID) => [
                'bookmark_id' => $bookmarkID,
                'folder_id' => $folderID->toInt()
            ])
            ->tap(fn (Collection $data) => FolderBookmark::insert($data->all()));

        $this->incrementFolderBookmarksCount($folderID, $bookmarkIDs->count());

        $this->updateTimeStamp($folderID);
    }

    private function updateTimeStamp(ResourceID $folderID): void
    {
        Model::query()->whereKey($folderID->toInt())->first()->touch();
    }

    /**
     * @return int number of deleted records.
     */
    public function removeBookmarksFromFolder(ResourceID $folderID, ResourceIDsCollection $bookmarkIDs): int
    {
        $deleted = FolderBookmark::where('folder_id', $folderID->toInt())->whereIn('bookmark_id', $bookmarkIDs->asIntegers()->all())->delete();

        if ($deleted > 0) {
            $this->updateTimeStamp($folderID);
        }

        return $deleted;
    }

    private function incrementFolderBookmarksCount(ResourceID $folderID, int $amount): void
    {
        $model = FolderBookmarksCount::query()->firstOrCreate(['folder_id' => $folderID->toInt()], ['count' => $amount]);

        if (!$model->wasRecentlyCreated) {
            $model->increment('count', $amount);
        }
    }
}
