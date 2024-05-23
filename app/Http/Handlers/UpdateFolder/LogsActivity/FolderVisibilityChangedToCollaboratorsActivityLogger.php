<?php

declare(strict_types=1);

namespace App\Http\Handlers\UpdateFolder\LogsActivity;

use App\Actions\CreateFolderActivity;
use App\DataTransferObjects\Activities\FolderVisibilityChangedToCollaboratorsOnlyActivityLogData;
use App\DataTransferObjects\UpdateFolderRequestData;
use App\Enums\ActivityType;
use App\Enums\FolderVisibility;
use App\Models\Folder;

final class FolderVisibilityChangedToCollaboratorsActivityLogger
{
    public function __construct(private readonly UpdateFolderRequestData $data)
    {
    }

    public function __invoke(Folder $folder): void
    {
        $createFolderActivity = new CreateFolderActivity(ActivityType::FOLDER_VISIBILITY_CHANGED_TO_COLLABORATORS_ONLY);

        $newVisibility = fn () => FolderVisibility::fromRequest($this->data->visibility);

        if ( ! $this->data->isUpdatingVisibility) {
            return;
        }

        if ( ! $newVisibility()->isVisibleToCollaboratorsOnly()) {
            return;
        }

        $createFolderActivity->create(
            $folder,
            new FolderVisibilityChangedToCollaboratorsOnlyActivityLogData($this->data->authUser)
        );
    }
}
