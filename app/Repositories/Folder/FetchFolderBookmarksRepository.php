<?php

declare(strict_types=1);

namespace App\Repositories\Folder;

use App\DataTransferObjects\Builders\BookmarkBuilder as Builder;
use App\DataTransferObjects\FolderBookmark;
use App\Models\Bookmark as BookmarkModel;
use App\PaginationData;
use App\QueryColumns\BookmarkAttributes;
use App\ValueObjects\ResourceID;
use App\ValueObjects\UserID;
use Illuminate\Database\Query\JoinClause;
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
        $result = BookmarkModel::WithQueryOptions(new BookmarkAttributes())
            ->addSelect('folders_bookmarks.is_public')
            ->join('folders_bookmarks', 'folders_bookmarks.bookmark_id', '=', 'bookmarks.id')
            ->where('folders_bookmarks.folder_id', $folderID->value())
            ->when($options['onlyPublic'], fn ($query) => $query->where('is_public', true))
            ->when(isset($options['userID']), function ($query) use ($options) {
                $query->addSelect('favourites.bookmark_id as isFavourite')
                    ->leftJoin('favourites', function (JoinClause $join) use ($options) {
                        $join->on('favourites.bookmark_id', '=', 'bookmarks.id')
                            ->where('favourites.user_id', $options['userID']->value());
                    });
            })
            ->simplePaginate($pagination->perPage(), [], page: $pagination->page());

        $result->setCollection(
            $result->getCollection()->map($this->buildFolderBookmarkObject($options))
        );

        return $result;
    }

    private function buildFolderBookmarkObject(array $options): \Closure
    {
        return function (BookmarkModel $model) use ($options) {
            $bookmark = Builder::fromModel($model)
                ->when(isset($options['userID']), fn (Builder $b) => $b->isUserFavorite((bool)$model->isFavourite))
                ->when(!isset($options['userID']), fn (Builder $b) => $b->isUserFavorite(false))
                ->build();

            return new FolderBookmark($bookmark, (bool) $model->is_public);
        };
    }
}
