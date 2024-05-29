<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder\Blacklisting;

use App\Http\Resources\PaginatedResourceCollection;
use App\Http\Resources\BlacklistedDomainResource;
use App\Models\User;
use App\PaginationData;
use App\ValueObjects\PublicId\FolderPublicId;
use Illuminate\Http\Request;
use App\Http\Handlers\Constraints;
use App\Http\Handlers\RequestHandlersQueue;
use App\Models\BlacklistedDomain;
use App\Models\Folder;
use App\Models\Scopes\WherePublicIdScope;

final class FetchBlacklistedDomainsController
{
    public function __invoke(Request $request, string $folderId): PaginatedResourceCollection
    {
        $folderId = FolderPublicId::fromRequest($folderId);

        $request->validate([
            //'domain' => ['sometimes', 'filled', 'string'],
            ...PaginationData::new()->asValidationRules()
        ]);

        $pagination = PaginationData::fromRequest($request);
        $requestHandlersQueue = new RequestHandlersQueue([
            new Constraints\FolderExistConstraint(),
            new Constraints\MustBeACollaboratorConstraint(User::fromRequest($request)),
        ]);

        $query = Folder::query()->select(['id'])->tap(new WherePublicIdScope($folderId));

        $requestHandlersQueue->scope($query);

        $requestHandlersQueue->handle($folder = $query->firstOrNew());

        $result = BlacklistedDomain::query()
            ->with(['collaborator'])
            ->where('folder_id', $folder->id)
            ->simplePaginate($pagination->perPage(), page: $pagination->page());

        return new PaginatedResourceCollection($result, BlacklistedDomainResource::class);
    }
}
