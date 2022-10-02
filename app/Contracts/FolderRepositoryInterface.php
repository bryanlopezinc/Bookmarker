<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DataTransferObjects\Folder;
use App\Exceptions\FolderNotFoundHttpResponseException;
use App\QueryColumns\FolderAttributes;
use App\ValueObjects\ResourceID;

interface FolderRepositoryInterface
{
    /**
     * @throws FolderNotFoundHttpResponseException
     */
    public function find(ResourceID $folderID, FolderAttributes $attributes = null): Folder;
}
