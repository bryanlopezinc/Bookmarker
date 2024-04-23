<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Http\Resources\MutedCollaboratorResource;
use App\Http\Resources\PaginatedResourceCollection;
use App\PaginationData;
use App\Services\Folder\FetchMutedCollaboratorsService as Service;
use App\ValueObjects\PublicId\FolderPublicId;
use Illuminate\Http\Request;

final class FetchMutedCollaboratorsController
{
    public function __invoke(Request $request, Service $service, string $folderId): PaginatedResourceCollection
    {
        $folderId = FolderPublicId::fromRequest($folderId);

        $request->validate([
            'name'      => ['sometimes', 'filled', 'string', 'max:10'],
            ...PaginationData::new()->asValidationRules()
        ]);

        $result = $service(
            $folderId,
            PaginationData::fromRequest($request),
            $request->input('name'),
        );

        return new PaginatedResourceCollection($result, MutedCollaboratorResource::class);
    }
}
