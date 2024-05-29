<?php

declare(strict_types=1);

namespace App\Http\Handlers\RevokeCollaboratorRole;

use App\Collections\RolesPublicIdsCollection;
use App\Models\Folder;
use App\Http\Handlers\Constraints;
use App\Http\Handlers\RequestHandlersQueue;
use App\Models\Scopes\WherePublicIdScope;
use App\Models\User;
use App\ValueObjects\PublicId\FolderPublicId;
use App\ValueObjects\PublicId\UserPublicId;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Handler
{
    public function handle(
        FolderPublicId $folderId,
        UserPublicId $collaboratorId,
        RolesPublicIdsCollection $roleIds,
        User $authUser
    ): void {
        $requestHandlersQueue = new RequestHandlersQueue(
            $this->getConfiguredHandlers($authUser, $roleIds)
        );

        $query = Folder::query()
            ->select(['id'])
            ->addSelect([
                'collaboratorId' => User::select('id')->tap(new WherePublicIdScope($collaboratorId))
            ])
            ->with([
                'roles' => function (HasMany $query) use ($roleIds) {
                    $query->tap(new WherePublicIdScope($roleIds->values()));
                }
            ])
            ->tap(new WherePublicIdScope($folderId));

        $requestHandlersQueue->scope($query);

        $requestHandlersQueue->handle($query->firstOrNew());
    }

    private function getConfiguredHandlers(User $authUser, RolesPublicIdsCollection $roleIds): array
    {
        return [
            new Constraints\FolderExistConstraint(),
            new Constraints\MustHaveRoleAccessConstraint($authUser),
            new RolesExistsConstraint($roleIds),
            new AffectedUserMustBeACollaboratorConstraint(),
            new CollaboratorMustHaveRolesConstraint($roleIds),
            new RevokeRoleCollaboratorRoles(),
        ];
    }
}
