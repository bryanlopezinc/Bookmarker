<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DataTransferObjects\Builders\FolderBuilder;
use App\DataTransferObjects\Folder;
use App\Exceptions\FolderNotFoundHttpResponseException;
use App\Models\Folder as Model;
use App\Models\UserFoldersCount;
use App\QueryColumns\FolderAttributes;
use App\ValueObjects\ResourceID;
use App\ValueObjects\UserID;

final class FoldersRepository
{
    public function create(Folder $folder): void
    {
        /** @var Model */
        $model = Model::query()->create([
            'description' => $folder->description->value,
            'name' => $folder->name->value,
            'user_id' => $folder->ownerID->toInt(),
            'created_at' => $folder->createdAt,
            'is_public' => $folder->isPublic
        ]);

        (new TagsRepository)->attach($folder->tags, $model);

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
    public function find(ResourceID $folderID, FolderAttributes $attributes = new FolderAttributes): Folder
    {
        /** @var Model */
        $model = Model::onlyAttributes($attributes)->whereKey($folderID->toInt())->first();

        if ($model === null) {
            throw new FolderNotFoundHttpResponseException;
        }

        return FolderBuilder::fromModel($model)->build();
    }

    public function update(ResourceID $folderID, Folder $newAttributes): void
    {
        $folder = Model::query()->whereKey($folderID->toInt())->first();

        $folder->update([
            'description' => $newAttributes->description->isEmpty() ? null : $newAttributes->description->value,
            'name' => $newAttributes->name->value,
            'is_public' => $newAttributes->isPublic
        ]);

        (new TagsRepository)->attach($newAttributes->tags, $folder);
    }
}