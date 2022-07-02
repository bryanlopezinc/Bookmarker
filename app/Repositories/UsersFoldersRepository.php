<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DataTransferObjects\Builders\FolderBuilder;
use App\DataTransferObjects\Folder;
use App\Enums\UserFoldersSortCriteria as SortCriteria;
use App\Models\Folder as Model;
use App\PaginationData;
use App\QueryColumns\FolderAttributes;
use App\ValueObjects\UserID;
use Illuminate\Pagination\Paginator;

final class UsersFoldersRepository
{
    /**
     * @return Paginator<Folder>
     */
    public function fetch(UserID $userID, PaginationData $pagination, SortCriteria $sortCriteria = SortCriteria::NEWEST): Paginator
    {
        $query = Model::onlyAttributes(new FolderAttributes())->where('user_id', $userID->toInt());

        $this->addSortQuery($query, $sortCriteria);

        /** @var Paginator */
        $result =  $query->simplePaginate($pagination->perPage(), page: $pagination->page());

        $result->setCollection(
            $result->getCollection()->map(function (Model $folder) {
                return FolderBuilder::fromModel($folder)->build();
            })
        );

        return $result;
    }

    private function addSortQuery(\Illuminate\Database\Eloquent\Builder &$query, SortCriteria $sortCriteria): void
    {
        match ($sortCriteria) {
            SortCriteria::NEWEST => $query->latest('folders.id'),
            SortCriteria::OLDEST => $query->oldest('folders.id'),
            SortCriteria::RECENTLY_UPDATED => $query->latest('folders.updated_at'),
            SortCriteria::MOST_ITEMS => $query->orderByDesc('bookmarks_count'), // bookmarks_count is alias from App\Models\Folder::scopeWithBookmarksCount()
            SortCriteria::LEAST_ITEMS => $query->orderBy('bookmarks_count'),
        };
    }
}
