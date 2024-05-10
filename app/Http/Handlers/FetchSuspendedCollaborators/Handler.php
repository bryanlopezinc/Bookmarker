<?php

declare(strict_types=1);

namespace App\Http\Handlers\FetchSuspendedCollaborators;

use App\Enums\Permission;
use App\Models\User;
use App\Models\Folder;
use App\PaginationData;
use App\Http\Handlers\Constraints;
use Illuminate\Pagination\Paginator;
use App\Models\SuspendedCollaborator;
use App\Models\Scopes\WherePublicIdScope;
use App\Http\Handlers\RequestHandlersQueue;
use App\ValueObjects\PublicId\FolderPublicId;

final class Handler
{
    /**
     * @return Paginator<SuspendedCollaborator>
     */
    public function handle(FolderPublicId $folderId, User $authUser, ?string $name, PaginationData $pagination): Paginator
    {
        $requestHandlersQueue = new RequestHandlersQueue([
            new Constraints\FolderExistConstraint(),
            new Constraints\MustBeACollaboratorConstraint($authUser),
            new Constraints\PermissionConstraint($authUser, Permission::SUSPEND_USER),
        ]);

        $handler = new FetchSuspendedCollaborators($name, $pagination);

        $query = Folder::query()->select(['id'])->tap(new WherePublicIdScope($folderId));

        $requestHandlersQueue->scope($query);

        $requestHandlersQueue->handle($folder = $query->firstOrNew());

        return $handler($folder);
    }
}
