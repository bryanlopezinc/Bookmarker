<?php

declare(strict_types=1);

namespace App\Http\Handlers\UpdateFolder;

use App\Models\Folder;
use App\Http\Handlers\Constraints;
use App\DataTransferObjects\UpdateFolderRequestData;
use App\Enums\CollaboratorMetricType;
use App\Http\Handlers\CollaboratorMetricsRecorder;
use App\Http\Handlers\RequestHandlersQueue;
use App\Http\Handlers\SuspendCollaborator\SuspendedCollaboratorFinder;
use App\Models\Scopes\WherePublicIdScope;
use App\ValueObjects\PublicId\FolderPublicId;

final class Handler
{
    public function handle(FolderPublicId $folderId, UpdateFolderRequestData $data): void
    {
        $requestHandlersQueue = new RequestHandlersQueue($this->getConfiguredHandlers($data));

        $query = Folder::query()->select(['id'])->tap(new WherePublicIdScope($folderId));

        $requestHandlersQueue->scope($query);

        $requestHandlersQueue->handle($query->firstOrNew());
    }

    private function getConfiguredHandlers(UpdateFolderRequestData $data): array
    {
        return [
            new Constraints\FolderExistConstraint(),
            $suspendedCollaboratorRepository = new SuspendedCollaboratorFinder($data->authUser),
            new Constraints\MustBeACollaboratorConstraint($data->authUser),
            new PermissionConstraint($data),
            new CanUpdateAttributesConstraint($data),
            new CannotMakeFolderWithCollaboratorPrivateConstraint($data),
            new FeatureMustBeEnabledConstraint($data),
            new Constraints\MustNotBeSuspendedConstraint($suspendedCollaboratorRepository),
            new PasswordCheckConstraint($data),
            new CanUpdateOnlyProtectedFolderPasswordConstraint($data),
            new UpdateFolder($data, new SendFolderUpdatedNotification($data)),
            new CollaboratorMetricsRecorder(CollaboratorMetricType::UPDATES, $data->authUser->id)
        ];
    }
}
