<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Collections\TagsCollection;
use App\DataTransferObjects\Builders\FolderBuilder;
use App\DataTransferObjects\Folder;
use App\Exceptions\FolderNotFoundHttpResponseException;
use App\Models\Folder as Model;
use App\Models\UserFoldersCount;
use App\QueryColumns\FolderAttributes;
use App\ValueObjects\FolderDescription;
use App\ValueObjects\FolderName;
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

    public function update(ResourceID $folderID, FolderName $folderName, FolderDescription $folderDescription, bool $isPublic): void
    {
        Model::query()->whereKey($folderID->toInt())->first()->update([
            'description' => $folderDescription->isEmpty() ? null : $folderDescription->value,
            'name' => $folderName->value,
            'is_public' => $isPublic
        ]);
    }
}
