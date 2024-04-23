<?php

declare(strict_types=1);

namespace App\Http\Handlers\RemoveRolePermission;

use App\Models\Folder;
use App\Http\Handlers\Constraints;
use App\Http\Handlers\RequestHandlersQueue;
use App\Models\FolderRole;
use App\Models\Scopes\WherePublicIdScope;
use App\Models\User;
use App\ValueObjects\PublicId\FolderPublicId;
use App\ValueObjects\PublicId\RolePublicId;

final class Handler
{
    public function handle(FolderPublicId $folderId, RolePublicId $roleId, string $permission, User $user): void
    {
        $requestHandlersQueue = new RequestHandlersQueue($this->getConfiguredHandlers($permission, $user));

        $query = Folder::query()
            ->tap(new WherePublicIdScope($folderId))
            ->select([
                'id',
                'roleId' => FolderRole::query()
                    ->select('id')
                    ->tap(new WherePublicIdScope($roleId))
                    ->whereColumn('folder_id', 'folders.id')
            ]);

        $requestHandlersQueue->scope($query);

        $requestHandlersQueue->handle($query->firstOrNew());
    }

    private function getConfiguredHandlers(string $permission, User $user): array
    {
        return [
            new Constraints\FolderExistConstraint(),
            new Constraints\RoleExistsConstraint(),
            new Constraints\CanCreateOrModifyRoleConstraint($user),
            new CannotRemoveAllRolePermissionsConstraint(),
            new DeleteRolePermission($permission)
        ];
    }
}
