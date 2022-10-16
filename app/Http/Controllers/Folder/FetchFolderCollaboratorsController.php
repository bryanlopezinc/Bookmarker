<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Http\Resources\FolderCollaboratorResource;
use App\Http\Resources\PaginatedResourceCollection;
use App\PaginationData;
use App\Rules\ResourceIdRule;
use App\Services\Folder\FetchFolderCollaboratorsService as Service;
use App\ValueObjects\ResourceID;
use Illuminate\Http\Request;

final class FetchFolderCollaboratorsController
{
    public function __invoke(Request $request, Service $service): PaginatedResourceCollection
    {
        $request->validate([
            'folder_id' => ['required', new ResourceIdRule],
            ...PaginationData::new()->asValidationRules()
        ]);

        $result = $service->get(
            ResourceID::fromRequest($request, 'folder_id'),
            PaginationData::fromRequest($request),
        );

        $result->appends('per_page', $request->input('per_page', PaginationData::DEFAULT_PER_PAGE))->withQueryString();

        return new PaginatedResourceCollection($result, FolderCollaboratorResource::class);
    }
}
