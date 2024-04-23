<?php

declare(strict_types=1);

namespace App\Http\Handlers\RemoveCollaborator;

use App\Models\Folder;
use App\DataTransferObjects\RemoveCollaboratorData;
use App\Enums\CollaboratorMetricType;
use App\Enums\Feature;
use App\Enums\Permission;
use App\Http\Handlers\Constraints;
use App\Http\Handlers\CollaboratorMetricsRecorder;
use App\Http\Handlers\RequestHandlersQueue;
use App\Models\Scopes\WherePublicIdScope;
use App\Models\User;

final class Handler
{
    public function handle(RemoveCollaboratorData $data): void
    {
        $query = Folder::query()
            ->select(['id'])
            ->addSelect(['collaboratorId' => User::select('id')->tap(new WherePublicIdScope($data->collaboratorId))])
            ->tap(new WherePublicIdScope($data->folderId));

        $requestHandlersQueue = new RequestHandlersQueue($this->getConfiguredHandlers($data));

        $requestHandlersQueue->scope($query);

        $requestHandlersQueue->handle($query->firstOrNew());
    }

    private function getConfiguredHandlers(RemoveCollaboratorData $data): array
    {
        return [
            new Constraints\FolderExistConstraint(),
            new Constraints\MustBeACollaboratorConstraint($data->authUser),
            new Constraints\PermissionConstraint($data->authUser, Permission::REMOVE_USER),
            new Constraints\FeatureMustBeEnabledConstraint($data->authUser, Feature::REMOVE_USER),
            new CannotRemoveSelfConstraint($data),
            new CannotRemoveFolderOwnerConstraint($data),
            new CollaboratorToBeRemovedMustBeACollaboratorConstraint($data),
            new RemoveCollaborator($data),
            new NotifyFolderOwner($data),
            new NotifyCollaborator(),
            new CollaboratorMetricsRecorder(CollaboratorMetricType::COLLABORATORS_REMOVED, $data->authUser->id)
        ];
    }
}
