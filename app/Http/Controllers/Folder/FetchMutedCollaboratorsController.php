<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Http\Resources\MutedCollaboratorResource;
use App\Http\Resources\PaginatedResourceCollection;
use App\PaginationData;
use App\Rules\ResourceIdRule;
use App\Services\Folder\FetchMutedCollaboratorsService as Service;
use Illuminate\Http\Request;

final class FetchMutedCollaboratorsController
{
    public function __invoke(Request $request, Service $service): PaginatedResourceCollection
    {
        $request->validate([
            'folder_id' => ['required', new ResourceIdRule()],
            'name'      => ['sometimes', 'filled', 'string', 'max:10'],
            ...PaginationData::new()->asValidationRules()
        ]);

        $result = $service(
            $request->integer('folder_id'),
            PaginationData::fromRequest($request),
            $request->input('name'),
        );

        return new PaginatedResourceCollection($result, MutedCollaboratorResource::class);
    }
}
