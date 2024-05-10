<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder\Suspension;

use App\Http\Handlers\FetchSuspendedCollaborators\Handler;
use App\Http\Resources\PaginatedResourceCollection;
use App\Http\Resources\SuspendedCollaboratorResource;
use App\Models\User;
use App\PaginationData;
use App\ValueObjects\PublicId\FolderPublicId;
use Illuminate\Http\Request;

final class FetchSuspendedCollaboratorsController
{
    public function __invoke(Request $request, Handler $service, string $folderId): PaginatedResourceCollection
    {
        $folderId = FolderPublicId::fromRequest($folderId);

        $request->validate([
            'name'      => ['sometimes', 'filled', 'string', 'max:10'],
            ...PaginationData::new()->asValidationRules()
        ]);

        $result = $service->handle(
            $folderId,
            User::fromRequest($request),
            $request->input('name'),
            PaginationData::fromRequest($request)
        );

        return new PaginatedResourceCollection($result, SuspendedCollaboratorResource::class);
    }
}
