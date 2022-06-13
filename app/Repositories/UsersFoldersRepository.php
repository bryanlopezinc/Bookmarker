<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DataTransferObjects\Builders\FolderBuilder;
use App\DataTransferObjects\Folder;
use App\Models\Folder as Model;
use App\PaginationData;
use App\ValueObjects\UserID;
use Illuminate\Pagination\Paginator;

final class UsersFoldersRepository
{
    /**
     * @return Paginator<Folder>
     */
    public function fetch(UserID $userID, PaginationData $pagination): Paginator
    {
        /** @var Paginator */
        $result =  Model::WithBookmarksCount()
            ->where('user_id', $userID->toInt())
            ->latest('folders.id')
            ->simplePaginate($pagination->perPage(), page: $pagination->page());

        $result->setCollection(
            $result->getCollection()->map(function (Model $folder) {
                return (new FolderBuilder())
                    ->setCreatedAt($folder->created_at)
                    ->setDescription($folder->description)
                    ->setID($folder->id)
                    ->setName($folder->name)
                    ->setOwnerID($folder->user_id)
                    ->setUpdatedAt($folder->updated_at)
                    ->setBookmarksCount((int)$folder->bookmarks_count)
                    ->setisPublic($folder->is_public)
                    ->build();
            })
        );

        return $result;
    }
}