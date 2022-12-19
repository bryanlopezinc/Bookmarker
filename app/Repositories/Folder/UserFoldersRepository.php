<?php

declare(strict_types=1);

namespace App\Repositories\Folder;

use App\DataTransferObjects\Builders\FolderBuilder;
use App\DataTransferObjects\Folder;
use App\Enums\UserFoldersSortCriteria as SortCriteria;
use App\Models\Folder as Model;
use App\PaginationData;
use App\QueryColumns\FolderAttributes;
use App\ValueObjects\UserID;
use Illuminate\Pagination\Paginator;

final class UserFoldersRepository
{
    /**
     * @return Paginator<Folder>
     */
    public function fetch(
        UserID $userID,
        PaginationData $pagination,
        SortCriteria $sortCriteria = SortCriteria::NEWEST
    ): Paginator {
        $query = Model::onlyAttributes(new FolderAttributes())
            ->where('user_id', $userID->value())
            ->with('tags', fn ($builder) => $builder->where('tags.created_by', $userID->value()));

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

            // bookmarks_count is alias from  folder bookmarks count query in App\Models\Folder
            SortCriteria::MOST_ITEMS => $query->orderByDesc('bookmarks_count'),

            SortCriteria::LEAST_ITEMS => $query->orderBy('bookmarks_count'),
        };
    }
}
