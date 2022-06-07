<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Collections\ResourceIDsCollection;
use App\DataTransferObjects\Builders\FolderBuilder;
use App\DataTransferObjects\Folder;
use App\Models\Folder as Model;
use App\Models\FolderBookmark;
use App\Models\FolderBookmarksCount;
use App\Models\UserFoldersCount;
use App\ValueObjects\ResourceID;
use App\ValueObjects\UserID;
use Illuminate\Support\Collection;

final class FoldersRepository
{
    public function create(Folder $folder): void
    {
        Model::query()->create([
            'description' => $folder->description->value,
            'name' => $folder->name->value,
            'user_id' => $folder->ownerID->toInt(),
            'created_at' => $folder->createdAt
        ]);

        $this->incrementUserFoldersCount($folder->ownerID);
    }

    private function incrementUserFoldersCount(UserID $userID): void
    {
        $model = UserFoldersCount::query()->firstOrCreate(['user_id' => $userID->toInt()], ['count' => 1]);

        if (!$model->wasRecentlyCreated) {
            $model->increment('count');
        }
    }

    /**
     * Find a folder by id or return false if the folder does not exists
     */
    public function findByID(ResourceID $folderID): Folder|false
    {
        $model = Model::query()->whereKey($folderID->toInt())->first();

        if ($model === null) {
            return false;
        }

        return (new FolderBuilder())
            ->setCreatedAt($model->created_at)
            ->setDescription($model->description)
            ->setName($model->name)
            ->setID($model->id)
            ->setOwnerID($model->user_id)
            ->build();
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
        $mapCallback = fn (int $bookmarkID) => [
            'bookmark_id' => $bookmarkID,
            'folder_id' => $folderID->toInt()
        ];

        FolderBookmark::insert($bookmarkIDs->asIntegers()->map($mapCallback)->all());

        $this->incrementFolderBookmarksCount($folderID, $bookmarkIDs->count());
    }

    private function incrementFolderBookmarksCount(ResourceID $folderID, int $amount): void
    {
        $attributes = [
            'folder_id' => $folderID->toInt(),
        ];

        $model = FolderBookmarksCount::query()->firstOrCreate($attributes, ['count' => $amount, ...$attributes]);

        if (!$model->wasRecentlyCreated) {
            $model->increment('count', $amount);
        }
    }
}