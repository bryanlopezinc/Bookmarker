<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Collections\ResourceIDsCollection;
use App\DataTransferObjects\Bookmark;
use App\DataTransferObjects\Builders\BookmarkBuilder;
use App\Models\Folder as Model;
use App\Models\FolderBookmark;
use App\Models\FolderBookmarksCount;
use App\PaginationData;
use App\ValueObjects\ResourceID;
use App\ValueObjects\UserID;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

final class FolderBookmarksRepository
{
    /**
     * @return Paginator<Bookmark>
     */
    public function bookmarks(ResourceID $folderID, PaginationData $pagination, UserID $userID): Paginator
    {
        /** @var Paginator */
        $result = FolderBookmark::query()
            ->where('folder_id', $folderID->toInt())
            ->simplePaginate($pagination->perPage(), ['bookmark_id'], page: $pagination->page());

        $bookmarkIDs = ResourceIDsCollection::fromNativeTypes($result->getCollection()->pluck('bookmark_id'));

        $favourites = (new FavouritesRepository)->getUserFavouritesFrom($bookmarkIDs, $userID)->asIntegers();

        $result->setCollection(
            (new BookmarksRepository)
                ->findManyById($bookmarkIDs)
                ->map(function (Bookmark $bookmark) use ($favourites) {
                    return BookmarkBuilder::fromBookmark($bookmark)
                        ->isUserFavourite($favourites->containsStrict($bookmark->id->toInt()))
                        ->build();
                })
        );

        return $result;
    }

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
