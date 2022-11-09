<?php

declare(strict_types=1);

namespace App\Repositories\Folder;

use App\Contracts\FolderRepositoryInterface;
use App\DataTransferObjects\Builders\FolderBuilder;
use App\DataTransferObjects\Folder;
use App\Exceptions\FolderNotFoundHttpResponseException;
use App\Models\DeletedUser;
use App\Models\Folder as Model;
use App\QueryColumns\FolderAttributes;
use App\Repositories\TagRepository;
use App\ValueObjects\ResourceID;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class FolderRepository implements FolderRepositoryInterface
{
    /**
     * @throws FolderNotFoundHttpResponseException
     */
    public function find(ResourceID $folderID, FolderAttributes $attributes = null): Folder
    {
        $attributes = $attributes ?: new FolderAttributes();

        try {
            $model = Model::onlyAttributes($attributes)
                ->whereKey($folderID->value())

                // All user folders are not deleted immediately when user deletes account but are deleted by
                // background tasks. This statement exists to ensure actions won't be performed on folders that
                // belongs to a deleted user account
                ->whereNotIn('folders.user_id', DeletedUser::select('user_id'))
                ->sole();

            return FolderBuilder::fromModel($model)->build();
        } catch (ModelNotFoundException) {
            throw new FolderNotFoundHttpResponseException;
        }
    }

    public function update(ResourceID $folderID, Folder $newAttributes): void
    {
        /** @var Model */
        $folder = Model::query()->whereKey($folderID->value())->sole();

        $folder->update([
            'description' => $newAttributes->description->isEmpty() ? null : $newAttributes->description->value,
            'name' => $newAttributes->name->value,
            'is_public' => $newAttributes->isPublic
        ]);

        (new TagRepository)->attach($newAttributes->tags, $folder);
    }
}
