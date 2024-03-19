<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Actions\FetchFolderCollaborators;
use App\Exceptions\FolderNotFoundException;
use App\Http\Resources\FolderCollaboratorResource;
use App\Http\Resources\PaginatedResourceCollection;
use App\Http\Requests\FetchFolderCollaboratorsRequest as Request;
use App\Models\Folder;

final class FetchFolderCollaboratorsController
{
    public function __invoke(Request $request, string $folderId): PaginatedResourceCollection
    {
        $folder = Folder::query()->select(['id', 'user_id'])->firstOrNew(['id' => $folderId]);

        if ( ! $folder->exists) {
            throw new FolderNotFoundException();
        }

        FolderNotFoundException::throwIfDoesNotBelongToAuthUser($folder);

        $result = (new FetchFolderCollaborators())->handle($request, (int) $folderId);

        return new PaginatedResourceCollection($result, FolderCollaboratorResource::class);
    }
}
