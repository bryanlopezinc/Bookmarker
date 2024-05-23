<?php

declare(strict_types=1);

namespace App\Http\Handlers\ReInstateSuspendedCollaborator;

use App\Enums\ActivityType;
use App\Http\Handlers\SuspendCollaborator\SuspendedCollaboratorFinder;
use App\Models\Folder;
use App\Models\FolderActivity;
use App\Models\User;
use App\DataTransferObjects\Activities\SuspensionLiftedActivityLogData as ActivityLogData;

final class LogActivity
{
    public function __construct(
        private readonly SuspendedCollaboratorFinder $repository,
        private readonly User $authUser
    ) {
    }

    public function __invoke(Folder $folder): void
    {
        $authUser = $this->authUser;

        $suspendedCollaboratorId = $this->repository->getRecord()->collaborator_id;

        dispatch(static function () use ($authUser, $suspendedCollaboratorId, $folder) {
            $suspendedCollaborator = User::query()
                ->whereKey($suspendedCollaboratorId)
                ->sole(['id', 'full_name', 'public_id', 'profile_image_path']);

            $activityData = new ActivityLogData($suspendedCollaborator, $authUser);

            FolderActivity::query()->create([
                'folder_id' => $folder->id,
                'type'      => ActivityType::SUSPENSION_LIFTED,
                'data'      => $activityData->toArray(),
            ]);
        })->afterResponse();
    }
}
