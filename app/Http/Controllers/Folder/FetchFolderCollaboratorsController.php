<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Http\Resources\FolderCollaboratorResource;
use App\Http\Resources\PaginatedResourceCollection;
use App\PaginationData;
use App\Services\Folder\FetchFolderCollaboratorsService as Service;
use App\ValueObjects\ResourceID;
use App\Http\Requests\FetchFolderCollaboratorsRequest as Request;
use App\UAC;

final class FetchFolderCollaboratorsController
{
    public function __invoke(Request $request, Service $service): PaginatedResourceCollection
    {
        $result = $service->get(
            ResourceID::fromRequest($request, 'folder_id'),
            PaginationData::fromRequest($request),
            $this->getFilter($request)
        );

        $result->appends('per_page', $request->input('per_page', PaginationData::DEFAULT_PER_PAGE))->withQueryString();

        return new PaginatedResourceCollection($result, FolderCollaboratorResource::class);
    }

    private function getFilter(Request $request): ?UAC
    {
        $filtersCount = count($request->validated('permissions', []));

        if ($filtersCount === 0) {
            return null;
        }

        if ($filtersCount === 4) {
            return UAC::all();
        }

        if ($request->validated('permissions.0') === 'view_only') {
            return UAC::fromArray(['read']);
        }

        return UAC::fromRequest($request, 'permissions');
    }
}
