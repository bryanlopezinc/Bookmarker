<?php

declare(strict_types=1);

namespace App\Http\Controllers\Folder\Roles;

use App\Actions\FetchFolderRoles;
use App\Http\Handlers\RequestHandlersQueue;
use App\Http\Handlers\Constraints;
use App\Models\Folder;
use App\Contracts\FolderRequestHandlerInterface as HandlerInterface;
use App\Http\Resources\FolderRoleResource;
use App\PaginationData;
use Illuminate\Http\Request;
use App\Http\Resources\PaginatedResourceCollection as ResourceCollection;
use App\Models\User;
use App\Rules\RoleNameRule;
use App\UAC;
use Illuminate\Validation\Rule;

final class FetchFolderRolesController
{
    public function __invoke(Request $request, string $folderId): ResourceCollection
    {
        $request->validate([
            'name'          => ['sometimes', new RoleNameRule()],
            'permissions'   => ['sometimes', 'array', 'filled', Rule::in(UAC::validExternalIdentifiers())],
            'permissions.*' => ['filled', 'distinct:strict'],
            ...PaginationData::new()->asValidationRules()
        ]);

        $query = Folder::query()->select(['id'])->whereKey($folderId);

        $requestHandlersQueue = new RequestHandlersQueue([
            new Constraints\FolderExistConstraint(),
            new Constraints\CanCreateOrModifyRoleConstraint(User::fromRequest($request)),
        ]);

        $requestHandlersQueue->scope($query);

        $folder = $query->firstOrNew();

        $requestHandlersQueue->handle(function (HandlerInterface $handler) use ($folder) {
            $handler->handle($folder);
        });

        $roles = (new FetchFolderRoles())->handle(
            (int) $folderId,
            PaginationData::fromRequest($request),
            UAC::fromRequest($request),
            $request->input('name'),
        );

        return new ResourceCollection($roles, FolderRoleResource::class);
    }
}
