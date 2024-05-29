<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder\Roles;

use App\Actions\FetchFolderRoles;
use App\Http\Handlers\RequestHandlersQueue;
use App\Http\Handlers\Constraints;
use App\Models\Folder;
use App\Http\Resources\FolderRoleResource;
use App\PaginationData;
use Illuminate\Http\Request;
use App\Http\Resources\PaginatedResourceCollection as ResourceCollection;
use App\Models\Scopes\WherePublicIdScope;
use App\Models\User;
use App\Rules\RoleNameRule;
use App\UAC;
use App\ValueObjects\PublicId\FolderPublicId;
use Illuminate\Validation\Rule;

final class FetchFolderRolesController
{
    public function __invoke(Request $request, string $folderId): ResourceCollection
    {
        $folderId = FolderPublicId::fromRequest($folderId);

        $request->validate([
            'name'          => ['sometimes', new RoleNameRule()],
            'permissions'   => ['sometimes', 'array', 'filled', Rule::in(UAC::validExternalIdentifiers())],
            'permissions.*' => ['filled', 'distinct:strict'],
            ...PaginationData::new()->asValidationRules()
        ]);

        $query = Folder::query()
            ->select(['id'])
            ->tap(new WherePublicIdScope($folderId));

        $requestHandlersQueue = new RequestHandlersQueue([
            new Constraints\FolderExistConstraint(),
            new Constraints\MustHaveRoleAccessConstraint(User::fromRequest($request)),
        ]);

        $requestHandlersQueue->scope($query);

        $requestHandlersQueue->handle($folder = $query->firstOrNew());

        $roles = (new FetchFolderRoles())->handle(
            $folder->id,
            PaginationData::fromRequest($request),
            UAC::fromRequest($request),
            $request->input('name'),
        );

        return new ResourceCollection($roles, FolderRoleResource::class);
    }
}
