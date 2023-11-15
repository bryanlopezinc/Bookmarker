<?php

declare(strict_types=1);

namespace App\Repositories\Folder;

use App\Enums\UserFoldersSortCriteria as SortCriteria;
use App\Models\Folder as Model;
use App\PaginationData;
use Illuminate\Pagination\Paginator;

final class UserFoldersRepository
{
    /**
     * @return Paginator<Model>
     */
    public function fetch(
        int $userId,
        PaginationData $pagination,
        SortCriteria $sortCriteria = SortCriteria::NEWEST
    ): Paginator {
        $query = Model::onlyAttributes()->where('user_id', $userId);

        $this->addSortQuery($query, $sortCriteria);

        return $query->simplePaginate($pagination->perPage(), page: $pagination->page());
    }

    private function addSortQuery(\Illuminate\Database\Eloquent\Builder &$query, SortCriteria $sortCriteria): void
    {
        match ($sortCriteria) {
            SortCriteria::NEWEST           => $query->latest('id'),
            SortCriteria::OLDEST           => $query->oldest('id'),
            SortCriteria::RECENTLY_UPDATED => $query->latest('updated_at'),
            SortCriteria::MOST_ITEMS       => $query->orderByDesc('bookmarksCount'),
            SortCriteria::LEAST_ITEMS      => $query->orderBy('bookmarksCount'),
        };
    }
}
