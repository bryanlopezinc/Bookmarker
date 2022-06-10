<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DataTransferObjects\Builders\FolderBuilder;
use App\DataTransferObjects\Folder;
use App\Models\Folder as Model;
use App\Models\UserFoldersCount;
use App\ValueObjects\FolderDescription;
use App\ValueObjects\FolderName;
use App\ValueObjects\ResourceID;
use App\ValueObjects\UserID;

final class FoldersRepository
{
    public function create(Folder $folder): void
    {
        Model::query()->create([
            'description' => $folder->description->value,
            'name' => $folder->name->value(),
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

    public function update(ResourceID $folderID, FolderName $folderName, FolderDescription $folderDescription): void
    {
        Model::query()->whereKey($folderID->toInt())->first()->update([
            'description' => $folderDescription->isEmpty() ? null : $folderDescription->value,
            'name' => $folderName->value()
        ]);
    }
}
