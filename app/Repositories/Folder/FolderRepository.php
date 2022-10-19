<?php

declare(strict_types=1);

namespace App\Repositories\Folder;

use App\Contracts\FolderRepositoryInterface;
use App\DataTransferObjects\Builders\FolderBuilder;
use App\DataTransferObjects\Folder;
use App\Exceptions\FolderNotFoundHttpResponseException;
use App\Models\Folder as Model;
use App\QueryColumns\FolderAttributes;
use App\ValueObjects\ResourceID;

final class FolderRepository implements FolderRepositoryInterface
{
    /**
     * @throws FolderNotFoundHttpResponseException
     */
    public function find(ResourceID $folderID, FolderAttributes $attributes = null): Folder
    {
        $attributes = $attributes ?: new FolderAttributes();

        /** @var Model|null */
        $model = Model::onlyAttributes($attributes)->whereKey($folderID->value())->first();

        if ($model === null) {
            throw new FolderNotFoundHttpResponseException;
        }

        return FolderBuilder::fromModel($model)->build();
    }
}
