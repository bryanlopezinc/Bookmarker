<?php

declare(strict_types=1);

namespace App\Http\Handlers\SuspendCollaborator;

use App\Enums\CollaboratorMetricType;
use App\Enums\Feature;
use App\Enums\Permission;
use App\Http\Handlers\CollaboratorMetricsRecorder;
use App\Models\Folder;
use App\Http\Handlers\Constraints;
use App\Http\Handlers\RequestHandlersQueue;
use App\Models\Scopes\WherePublicIdScope;
use App\Models\User;
use App\ValueObjects\PublicId\FolderPublicId;
use App\ValueObjects\PublicId\UserPublicId;

final class Handler
{
    public function handle(FolderPublicId $folderId, UserPublicId $collaboratorId, ?int $suspensionPeriodInHours, User $authUser): void
    {
        $requestHandlersQueue = new RequestHandlersQueue(
            $this->getConfiguredHandlers($authUser, $suspensionPeriodInHours)
        );

        $query = Folder::query()->select([
            'id',
            'collaboratorId' => User::select('id')->tap(new WherePublicIdScope($collaboratorId))
        ]);

        $query->tap(new WherePublicIdScope($folderId));

        $requestHandlersQueue->scope($query);

        $requestHandlersQueue->handle($query->firstOrNew());
    }

    private function getConfiguredHandlers(User $authUser, ?int $suspensionPeriodInHours): array
    {
        return [
            new Constraints\FolderExistConstraint(),
            $finder = new SuspendedCollaboratorFinder(),
            new Constraints\MustBeACollaboratorConstraint($authUser),
            new Constraints\PermissionConstraint($authUser, Permission::SUSPEND_USER),
            new Constraints\FeatureMustBeEnabledConstraint($authUser, Feature::SUSPEND_USER),
            new CollaboratorExistsConstraint(),
            new CannotSuspendSelfConstraint($authUser),
            new CannotSuspendFolderOwnerConstraint(),
            new AffectedUserMustBeACollaboratorConstraint(),
            new CannotSuspendCollaboratorMoreThanOnceConstraint($finder),
            new SuspendCollaborator($suspensionPeriodInHours, $finder, $authUser),
            new CollaboratorMetricsRecorder(CollaboratorMetricType::SUSPENDED_COLLABORATORS, $authUser->id)
        ];
    }
}
