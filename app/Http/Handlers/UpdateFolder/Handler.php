<?php

declare(strict_types=1);

namespace App\Http\Handlers\UpdateFolder;

use App\Models\Folder;
use App\Http\Handlers\Constraints;
use App\Contracts\FolderRequestHandlerInterface as HandlerInterface;
use App\DataTransferObjects\UpdateFolderRequestData;
use App\Enums\CollaboratorMetricType;
use App\Enums\Feature;
use App\Enums\Permission;
use App\Http\Handlers\CollaboratorMetricsRecorder;
use App\Http\Handlers\RequestHandlersQueue;

final class Handler
{
    public function handle(int $folderId, UpdateFolderRequestData $data): void
    {
        $requestHandlersQueue = new RequestHandlersQueue($this->getConfiguredHandlers($data));

        $query = Folder::query()->select(['id'])->whereKey($folderId);

        $requestHandlersQueue->scope($query);

        $folder = $query->firstOrNew();

        $requestHandlersQueue->handle(function (HandlerInterface $handler) use ($folder) {
            $handler->handle($folder);
        });
    }

    private function getConfiguredHandlers(UpdateFolderRequestData $data): array
    {
        return [
            new Constraints\FolderExistConstraint(),
            new Constraints\MustBeACollaboratorConstraint($data->authUser),
            new Constraints\PermissionConstraint($data->authUser, Permission::UPDATE_FOLDER),
            new CanUpdateAttributesConstraint($data),
            new CannotMakeFolderWithCollaboratorPrivateConstraint($data),
            new Constraints\FeatureMustBeEnabledConstraint($data->authUser, Feature::UPDATE_FOLDER),
            new PasswordCheckConstraint($data),
            new CanUpdateOnlyProtectedFolderPasswordConstraint($data),
            new UpdateFolder($data, new SendFolderUpdatedNotification($data)),
            new CollaboratorMetricsRecorder(CollaboratorMetricType::UPDATES, $data->authUser->id)
        ];
    }
}
