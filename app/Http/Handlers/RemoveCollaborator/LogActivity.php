<?php

declare(strict_types=1);

namespace App\Http\Handlers\RemoveCollaborator;

use App\Enums\ActivityType;
use App\Models\Folder;
use App\Models\FolderActivity;
use App\Models\User;
use App\DataTransferObjects\Activities\CollaboratorRemovedActivityLogData as ActivityLogData;

final class LogActivity
{
    public function __construct(private readonly User $authUser)
    {
    }

    /**
     * @see \App\Http\Handlers\RemoveCollaborator\NotifyCollaborator for 'collaboratorRemoved' variable
     */
    public function __invoke(Folder $folder): void
    {
        $authUser = $this->authUser;

        $removedCollaboratorRecord = $folder->collaboratorRemoved;

        $folderId = $folder->id;

        dispatch(static function () use ($folderId, $authUser, $removedCollaboratorRecord) {
            $activityData = new ActivityLogData(
                new User($removedCollaboratorRecord),
                $authUser
            );

            FolderActivity::query()->create([
                'folder_id' => $folderId,
                'type'      => ActivityType::COLLABORATOR_REMOVED,
                'data'      => $activityData->toArray(),
            ]);
        })->afterResponse();
    }
}
