<?php

declare(strict_types=1);

namespace App\Http\Handlers\ReInstateSuspendedCollaborator;

use App\Http\Handlers\ConditionallyLogActivity;
use App\Models\Folder;
use App\Http\Handlers\Constraints;
use App\Http\Handlers\RequestHandlersQueue;
use App\Http\Handlers\SuspendCollaborator\AffectedUserMustBeACollaboratorConstraint;
use App\Http\Handlers\SuspendCollaborator\CollaboratorExistsConstraint;
use App\Http\Handlers\SuspendCollaborator\SuspendedCollaboratorFinder;
use App\Models\Scopes\WherePublicIdScope;
use App\Models\User;
use App\ValueObjects\PublicId\FolderPublicId;
use App\ValueObjects\PublicId\UserPublicId;

final class Handler
{
    public function handle(FolderPublicId $folderId, UserPublicId $collaboratorId, User $authUser): void
    {
        $requestHandlersQueue = new RequestHandlersQueue(
            $this->getConfiguredHandlers($authUser)
        );

        $query = Folder::query()
            ->select([
                'id',
                'collaboratorId' => User::select('id')->tap(new WherePublicIdScope($collaboratorId))
            ])
            ->tap(new WherePublicIdScope($folderId));

        $requestHandlersQueue->scope($query);

        $requestHandlersQueue->handle($query->firstOrNew());
    }

    private function getConfiguredHandlers(User $authUser): array
    {
        return [
            new Constraints\FolderExistConstraint(),
            $finder = new SuspendedCollaboratorFinder(),
            new FolderMustBelongToUserConstraint($authUser),
            new CollaboratorExistsConstraint(),
            new AffectedUserMustBeACollaboratorConstraint(),
            new MustBeSuspendedConstraint($finder),
            new ReInstateSuspendedCollaborator($finder),
            new ConditionallyLogActivity(new LogActivity($finder, $authUser))
        ];
    }
}
