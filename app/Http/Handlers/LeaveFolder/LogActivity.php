<?php

declare(strict_types=1);

namespace App\Http\Handlers\LeaveFolder;

use App\DataTransferObjects\Activities\CollaboratorExitActivityLogData;
use App\Enums\ActivityType;
use App\Models\Folder;
use App\Models\FolderActivity;
use App\Models\User;

final class LogActivity
{
    public function __construct(private readonly User $authUser)
    {
    }

    public function __invoke(Folder $folder): void
    {
        $attributes = [
            'folder_id' => $folder->id,
            'type'      => ActivityType::COLLABORATOR_EXIT,
            'data'      => (new CollaboratorExitActivityLogData($this->authUser))->toArray(),
        ];

        dispatch(function () use ($attributes) {
            FolderActivity::query()->create($attributes);
        })->afterResponse();
    }
}
