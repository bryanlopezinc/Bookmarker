<?php

declare(strict_types=1);

namespace App\Repositories\Folder;

use App\DataTransferObjects\Builders\FolderBuilder;
use App\DataTransferObjects\Folder;
use App\Exceptions\FolderNotFoundHttpResponseException;
use App\Models\Folder as Model;
use App\QueryColumns\FolderAttributes;
use App\ValueObjects\ResourceID;

final class FolderRepository
{
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
}