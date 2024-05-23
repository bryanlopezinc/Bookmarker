<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder;

use App\Http\Resources\Activity\Resource;
use App\Http\Resources\PaginatedResourceCollection;
use App\Models\User;
use App\PaginationData;
use App\Services\Folder\FetchFolderActivitiesService;
use App\ValueObjects\PublicId\FolderPublicId;
use Illuminate\Http\Request;

final class FetchFolderActivitiesController
{
    public function __invoke(Request $request, FetchFolderActivitiesService $service, string $folderId): PaginatedResourceCollection
    {
        $request->validate(PaginationData::new()->asValidationRules());

        $activities = $service(
            FolderPublicId::fromRequest($folderId),
            User::fromRequest($request),
            PaginationData::fromRequest($request)
        );

        return new PaginatedResourceCollection($activities, Resource::class);
    }
}
