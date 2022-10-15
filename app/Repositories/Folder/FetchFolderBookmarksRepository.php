<?php

declare(strict_types=1);

namespace App\Repositories\Folder;

use App\Collections\ResourceIDsCollection as IDs;
use App\DataTransferObjects\Bookmark;
use App\DataTransferObjects\Builders\BookmarkBuilder;
use App\DataTransferObjects\FolderBookmark;
use App\Models\FolderBookmark as Model;
use App\PaginationData;
use App\Repositories\FavoriteRepository;
use App\Repositories\FetchBookmarksRepository;
use App\ValueObjects\ResourceID;
use App\ValueObjects\UserID;
use Illuminate\Pagination\Paginator;

final class FetchFolderBookmarksRepository
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
        $result = Model::query()
            ->where('folder_id', $folderID->toInt())
            ->when($options['onlyPublic'], fn ($query) => $query->where('is_public', true))
            ->simplePaginate($pagination->perPage(), ['bookmark_id', 'is_public'], page: $pagination->page());

        $bookmarkIDs = IDs::fromNativeTypes($result->getCollection()->pluck('bookmark_id'));

        $favorites = isset($options['userID'])
            ? (new FavoriteRepository)->intersect($bookmarkIDs, $options['userID'])->asIntegers()
            : collect([]);

        $result->setCollection(
            (new FetchBookmarksRepository)
                ->findManyById($bookmarkIDs)
                ->map(function (Bookmark $bookmark) use ($favorites, $result) {
                    $bookmark = BookmarkBuilder::fromBookmark($bookmark)
                        ->isUserFavorite($favorites->containsStrict($bookmark->id->toInt()))
                        ->build();

                    return new FolderBookmark(
                        $bookmark,
                        $result->getCollection()->filter(fn (Model $model) => $model->bookmark_id === $bookmark->id->toInt())->sole()->is_public
                    );
                })
        );

        return $result;
    }

    /**
     * Check if ANY the given bookmarks exists in the given folder
     */
    public function contains(IDs $bookmarkIDs, ResourceID $folderID): bool
    {
        if ($bookmarkIDs->isEmpty()) {
            return false;
        }

        return Model::where('folder_id', $folderID->toInt())
            ->whereIn('bookmark_id', $bookmarkIDs->asIntegers()->unique()->all())
            ->count() > 0;
    }

    /**
     * Check if ALL the given bookmarks exists in the given folder
     */
    public function containsAll(IDs $bookmarkIDs, ResourceID $folderID): bool
    {
        if ($bookmarkIDs->isEmpty()) {
            return false;
        }

        return Model::where('folder_id', $folderID->toInt())
            ->whereIn('bookmark_id', $bookmarkIDs->asIntegers()->unique()->all())
            ->count() === $bookmarkIDs->count();
    }
}
