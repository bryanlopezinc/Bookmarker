<?php

declare(strict_types=1);

namespace App\Http\Handlers\LeaveFolder;

use App\Models\Folder;
use App\Http\Handlers\Constraints;
use App\Http\Handlers\ConditionallyLogActivity;
use App\Http\Handlers\RequestHandlersQueue;
use App\Models\Scopes\WherePublicIdScope;
use App\Models\User;
use App\ValueObjects\PublicId\FolderPublicId;

final class Handler
{
    public function handle(FolderPublicId $folderId, User $authUser): void
    {
        $requestHandlersQueue = new RequestHandlersQueue($this->getConfiguredHandlers($authUser));

        $query = Folder::query()
            ->select(['id'])
            ->tap(new WherePublicIdScope($folderId));

        $requestHandlersQueue->scope($query);

        $requestHandlersQueue->handle($query->firstOrNew());
    }

    private function getConfiguredHandlers(User $authUser): array
    {
        return [
            new Constraints\FolderExistConstraint(),
            new Constraints\MustBeACollaboratorConstraint($authUser),
            new CannotLeaveOwnFolderConstraint($authUser),
            new RemoveCollaborator($authUser),
            new SendNotifications($authUser),
            new RemovePermissions($authUser),
            new ConditionallyLogActivity(new LogActivity($authUser))
        ];
    }
}
