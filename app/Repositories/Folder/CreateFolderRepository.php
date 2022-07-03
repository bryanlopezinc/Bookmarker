<?php

declare(strict_types=1);

namespace App\Repositories\Folder;

use App\DataTransferObjects\Folder;
use App\Models\Folder as Model;
use App\Models\UserFoldersCount;
use App\Repositories\TagsRepository;
use App\ValueObjects\UserID;

final class CreateFolderRepository
{
    public function __construct(private TagsRepository $tagsRepository)
    {
    }

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

        $this->tagsRepository->attach($folder->tags, $model);

        $this->incrementUserFoldersCount($folder->ownerID);
    }

    private function incrementUserFoldersCount(UserID $userID): void
    {
        $model = UserFoldersCount::query()->firstOrCreate(['user_id' => $userID->toInt()], ['count' => 1]);

        if (!$model->wasRecentlyCreated) {
            $model->increment('count');
        }
    }
}
