<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DataTransferObjects\Folder;
use App\Models\Folder as Model;
use App\Models\UserResourcesCount;
use App\ValueObjects\UserID;

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
        $attributes = [
            'user_id' => $userID->toInt(),
            'type' => UserResourcesCount::FOLDERS_TYPE,
        ];

        $model = UserResourcesCount::query()->firstOrCreate($attributes, ['count' => 1, ...$attributes]);

        if (!$model->wasRecentlyCreated) {
            $model->increment('count');
        }
    }
}
