<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Collections\ResourceIDsCollection as IDs;
use App\DataTransferObjects\Bookmark;
use App\DataTransferObjects\Builders\BookmarkBuilder;
use App\DataTransferObjects\FolderBookmark;
use App\Models\Folder as Model;
use App\Models\FolderBookmark as FolderBookmarkModel;
use App\Models\FolderBookmarksCount;
use App\PaginationData;
use App\ValueObjects\ResourceID;
use App\ValueObjects\UserID;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

final class FolderBookmarksRepository
{
    /**
     * @return Paginator<FolderBookmark>
     */
    public function bookmarks(ResourceID $folderID, PaginationData $pagination, UserID $userID): Paginator
    {
        return $this->getBookmarks($folderID, $pagination, [
            'onlyPublic' => false,
            'userID' => $userID
        ]);
    }

    /**
     * @return Paginator<FolderBookmark>
     */
    public function onlyPublicBookmarks(ResourceID $folderID, PaginationData $pagination): Paginator
    {
        return $this->getBookmarks($folderID, $pagination, [
            'onlyPublic' => true
        ]);
    }

    /**
     * @return Paginator<FolderBookmark>
     */
    private function getBookmarks(ResourceID $folderID, PaginationData $pagination, array $options): Paginator
    {
        /** @var Paginator */
        $result = FolderBookmarkModel::query()
            ->where('folder_id', $folderID->toInt())
            ->when($options['onlyPublic'], fn ($query) => $query->where('is_public', true))
            ->simplePaginate($pagination->perPage(), ['bookmark_id', 'is_public'], page: $pagination->page());

        $bookmarkIDs = IDs::fromNativeTypes($result->getCollection()->pluck('bookmark_id'));

        $favourites = isset($options['userID'])
            ? (new FavouritesRepository)->getUserFavouritesFrom($bookmarkIDs, $options['userID'])->asIntegers()
            : collect();

        $result->setCollection(
            (new BookmarksRepository)
                ->findManyById($bookmarkIDs)
                ->map(function (Bookmark $bookmark) use ($favourites, $result) {
                    $bookmark = BookmarkBuilder::fromBookmark($bookmark)
                        ->isUserFavourite($favourites->containsStrict($bookmark->id->toInt()))
                        ->build();

                    return new FolderBookmark(
                        $bookmark,
                        $result->getCollection()->filter(fn (FolderBookmarkModel $model) => $model->bookmark_id === $bookmark->id->toInt())->sole()->is_public
                    );
                })
        );

        return $result;
    }

    /**
     * Get all the bookmarkIDs that already exists in  given folder from the given bookmark ids.
     */
    public function getFolderBookmarksFrom(ResourceID $folderID, IDs $bookmarkIDs): IDs
    {
        return FolderBookmarkModel::where('folder_id', $folderID->toInt())
            ->whereIn('bookmark_id', $bookmarkIDs->asIntegers()->unique()->all())
            ->get('bookmark_id')
            ->pipe(fn (Collection $bookmarkIDs) => IDs::fromNativeTypes($bookmarkIDs->pluck('bookmark_id')->all()));
    }

    public function addBookmarksToFolder(ResourceID $folderID, IDs $bookmarkIDs, IDs $makeHidden): void
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

        $this->updateTimeStamp($folderID);
    }

    private function updateTimeStamp(ResourceID $folderID): void
    {
        Model::query()->whereKey($folderID->toInt())->first()->touch();
    }

    /**
     * @return int number of deleted records.
     */
    public function removeBookmarksFromFolder(ResourceID $folderID, IDs $bookmarkIDs): int
    {
        $deleted = FolderBookmarkModel::where('folder_id', $folderID->toInt())->whereIn('bookmark_id', $bookmarkIDs->asIntegers()->all())->delete();

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

    public function makeHidden(ResourceID $folderID, IDs $bookmarkIDs): void
    {
        FolderBookmarkModel::where('folder_id', $folderID->toInt())
            ->whereIn('bookmark_id', $bookmarkIDs->asIntegers()->all())
            ->update([
                'is_public' => false
            ]);
    }
}