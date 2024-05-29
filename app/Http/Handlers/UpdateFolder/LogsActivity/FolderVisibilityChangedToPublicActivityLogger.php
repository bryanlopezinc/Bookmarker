<?php

declare(strict_types=1);

namespace App\Http\Handlers\UpdateFolder\LogsActivity;

use App\Actions\CreateFolderActivity;
use App\DataTransferObjects\Activities\FolderVisibilityChangedToPublicActivityLogData;
use App\DataTransferObjects\UpdateFolderRequestData;
use App\Enums\ActivityType;
use App\Enums\FolderVisibility;
use App\Models\Folder;

final class FolderVisibilityChangedToPublicActivityLogger
{
    public function __construct(private readonly UpdateFolderRequestData $data)
    {
    }

    public function __invoke(Folder $folder): void
    {
        $newVisibility = fn () => FolderVisibility::fromRequest($this->data->visibility);

        $createFolderActivity = new CreateFolderActivity(ActivityType::FOLDER_VISIBILITY_CHANGED_TO_PUBLIC);

        if ( ! $this->data->isUpdatingVisibility) {
            return;
        }

        if ( ! $newVisibility()->isPublic()) {
            return;
        }

        $createFolderActivity->create(
            $folder,
            new FolderVisibilityChangedToPublicActivityLogData($this->data->authUser)
        );
    }
}
