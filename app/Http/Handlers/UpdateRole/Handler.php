<?php

declare(strict_types=1);

namespace App\Http\Handlers\UpdateRole;

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
    public function handle(FolderPublicId $folderId, User $user, RolePublicId $roleId, string $role): void
    {
        $requestHandlersQueue = new RequestHandlersQueue($this->getConfiguredHandlers($user, $role));

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

    private function getConfiguredHandlers(User $user, string $role): array
    {
        return [
            new Constraints\FolderExistConstraint(),
            new Constraints\MustHaveRoleAccessConstraint($user),
            new Constraints\RoleExistsConstraint(),
            new Constraints\UniqueRoleNameConstraint($role),
            new UpdateFolderRole($role)
        ];
    }
}
