<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DataTransferObjects\Builders\FolderBuilder;
use App\DataTransferObjects\Folder;
use App\Exceptions\FolderNotFoundHttpResponseException;
use App\Models\Folder as Model;
use App\Models\UserFoldersCount;
use App\ValueObjects\FolderDescription;
use App\ValueObjects\FolderName;
use App\ValueObjects\ResourceID;
use App\ValueObjects\UserID;
use Throwable;

final class FoldersRepository
{
    public function create(Folder $folder): void
    {
        Model::query()->create([
            'description' => $folder->description->value,
            'name' => $folder->name->value,
            'user_id' => $folder->ownerID->toInt(),
            'created_at' => $folder->createdAt,
            'is_public' => $folder->isPublic
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
     * @throws FolderNotFoundHttpResponseException
     */
    public function find(ResourceID $folderID): Folder
    {
        $model = Model::WithBookmarksCount()->whereKey($folderID->toInt())->first();

        if ($model === null) {
           throw new FolderNotFoundHttpResponseException;
        }

        return (new FolderBuilder())
            ->setCreatedAt($model->created_at)
            ->setDescription($model->description)
            ->setName($model->name)
            ->setID($model->id)
            ->setOwnerID($model->user_id)
            ->setisPublic($model->is_public)
            ->setBookmarksCount((int)$model->bookmarks_count)
            ->build();
    }

    public function update(ResourceID $folderID, FolderName $folderName, FolderDescription $folderDescription, bool $isPublic): void
    {
        Model::query()->whereKey($folderID->toInt())->first()->update([
            'description' => $folderDescription->isEmpty() ? null : $folderDescription->value,
            'name' => $folderName->value,
            'is_public' => $isPublic
        ]);
    }
}