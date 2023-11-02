<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Http\Resources\FolderCollaboratorResource;
use App\Http\Resources\PaginatedResourceCollection;
use App\Services\Folder\FetchFolderCollaboratorsService as Service;
use App\Http\Requests\FetchFolderCollaboratorsRequest as Request;

final class FetchFolderCollaboratorsController
{
    public function __invoke(Request $request, Service $service): PaginatedResourceCollection
    {
        $result = $service->fromRequest($request);

        return new PaginatedResourceCollection($result, FolderCollaboratorResource::class);
    }
}
